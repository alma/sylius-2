<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * Idempotent transition of the Sylius Payment to `completed` once Alma reports
 * one of the order's Alma payments `authorized` or `captured` (anti-fraud
 * checks already passed).
 *
 * Shared between the customer return route and the IPN handler. The helper
 * applies only the `payment.complete` transition; everything downstream
 * (order_payment → paid, inventory release, order state resolution,
 * invoice/email if the merchant has the relevant plugins installed) cascades
 * natively through the Sylius 2.0 workflow listeners.
 *
 * An order MAY carry several Alma payments, all on its single Sylius Payment
 * (cf. specs-sylius/in-page). Before completing, the Sylius Payment is made to
 * refer to the Alma payment that paid — so the native refund targets it — and
 * gets the Alma method back if the customer had switched to another one whose
 * payment has not started. On an order already paid, or while another method's
 * payment is under way, no transition is applied: the extra paid Alma payment
 * is only recorded as paid and logged.
 *
 * Idempotence is guaranteed by the abstract base via `StateMachineInterface::can()`
 * — replaying `complete()` on a payment already `completed` is a silent no-op.
 */
class AlmaPaymentCompletionHelper extends AbstractPaymentTransitionHelper
{
    public function __construct(
        StateMachineInterface $stateMachine,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger = new NullLogger(),
        private readonly ?AlmaPaymentMethodResolver $methodResolver = null,
    ) {
        parent::__construct($stateMachine, $entityManager, $logger);
    }

    public function complete(ResolvedPayment $resolved): bool
    {
        $reference = $resolved->reference;
        $newlyPaid = $reference !== null && !$reference->isPaid();
        $reference?->markPaid(new \DateTimeImmutable());
        if ($reference !== null && $reference->getFeePlanKey() === null) {
            $reference->setFeePlanKey(FeePlanKey::ofGeneralPlan(
                $resolved->almaPayment->getInstallmentsCount(),
                $resolved->almaPayment->getDeferredDays(),
                $resolved->almaPayment->getDeferredMonths(),
            )->value);
        }

        if (AlreadyPaidOrder::is($resolved->order)) {
            $this->logger->warning('alma.payment.paid_on_already_paid_order', [
                'order_number' => $resolved->order->getNumber(),
                'alma_payment_id' => $resolved->almaPayment->getId(),
                'order_alma_payment_id' => $resolved->syliusPayment->getDetails()[AlmaPaymentDetailsKeys::PAYMENT_ID] ?? null,
                'mode' => $resolved->almaMode,
            ]);
            if ($newlyPaid) {
                $this->entityManager->flush();
            }

            return false;
        }

        if ($this->isOtherMethodUnderWay($resolved->syliusPayment)) {
            $this->logger->warning('alma.payment.paid_while_other_method_under_way', [
                'order_number' => $resolved->order->getNumber(),
                'alma_payment_id' => $resolved->almaPayment->getId(),
                'payment_state' => $resolved->syliusPayment->getState(),
                'mode' => $resolved->almaMode,
            ]);
            if ($newlyPaid) {
                $this->entityManager->flush();
            }

            return false;
        }

        $applied = $this->doApply($resolved);
        if (!$applied && $newlyPaid) {
            $this->entityManager->flush();
        }

        return $applied;
    }

    /**
     * Another gateway's payment has started (`processing`, `authorized`) and
     * may still go through: taking it over could charge the customer twice.
     */
    private function isOtherMethodUnderWay(PaymentInterface $payment): bool
    {
        $method = $payment->getMethod();
        if ($method === null || $this->methodResolver === null || $this->methodResolver->isAlma($method)) {
            return false;
        }

        return !\in_array($payment->getState(), [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW], true);
    }

    protected function getTransition(): string
    {
        return PaymentTransitions::TRANSITION_COMPLETE;
    }

    protected function getSkippedLogKey(): string
    {
        return 'alma.payment.completion_skipped';
    }

    protected function getAppliedLogKey(): string
    {
        return 'alma.payment.completed';
    }

    protected function beforeTransition(ResolvedPayment $resolved): void
    {
        $payment = $resolved->syliusPayment;

        $method = $payment->getMethod();
        $almaMethod = $this->methodResolver?->getAlmaPaymentMethod($resolved->order->getChannel());
        if ($almaMethod !== null && ($method === null || !$this->methodResolver->isAlma($method))) {
            $payment->setMethod($almaMethod);
        }

        $details = $payment->getDetails();
        $details[AlmaPaymentDetailsKeys::PAYMENT_ID] = $resolved->almaPayment->getId();
        $details[AlmaPaymentDetailsKeys::MODE] = $resolved->almaMode;
        $planKey = $resolved->reference?->getFeePlanKey();
        if ($planKey !== null) {
            $details[AlmaPaymentDetailsKeys::FEE_PLAN_KEY] = $planKey;
        }
        $payment->setDetails($details);
    }
}
