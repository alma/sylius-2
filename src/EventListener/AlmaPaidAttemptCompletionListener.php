<?php

declare(strict_types=1);

namespace Alma\Sylius\EventListener;

use Alma\Sylius\Entity\AlmaPaymentReference;
use Alma\Sylius\Payment\AlmaFraudChecker;
use Alma\Sylius\Payment\AlmaPaymentCompletionHelper;
use Alma\Sylius\Payment\AlmaPaymentResolver;
use Alma\Sylius\Payment\AlreadyPaidOrder;
use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Completes the order with an Alma attempt paid while another gateway's
 * payment was under way, once that payment fails or is cancelled
 * (cf. specs-sylius/in-page). Alma sends nothing more by then: without this,
 * the order would stay awaiting payment while the customer was charged.
 *
 * Runs after Sylius' own listener (priority 100), which gives the order a new
 * Sylius Payment in state `new`; the paid attempt then goes through the same
 * resolution, checks and completion as on the IPN.
 */
final class AlmaPaidAttemptCompletionListener
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $methodResolver,
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
        private readonly AlmaPaymentResolver $paymentResolver,
        private readonly AlmaFraudChecker $fraudChecker,
        private readonly AlmaPaymentCompletionHelper $completionHelper,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof PaymentInterface) {
            return;
        }

        $method = $payment->getMethod();
        $order = $payment->getOrder();
        if ($method === null || $this->methodResolver->isAlma($method) || !$order instanceof OrderInterface) {
            return;
        }

        if (AlreadyPaidOrder::is($order) || $order->getLastPayment(PaymentInterface::STATE_NEW) === null) {
            return;
        }

        $reference = $this->firstPaidAttempt($order);
        if ($reference === null) {
            return;
        }

        $resolved = $this->paymentResolver->resolve($reference->getAlmaPaymentId());
        if ($resolved === null) {
            return;
        }

        $checkResult = $this->fraudChecker->check($resolved);
        if (!$checkResult->passed) {
            $this->logger->warning('alma.payment.paid_attempt_not_applied', [
                'order_number' => $order->getNumber(),
                'alma_payment_id' => $reference->getAlmaPaymentId(),
                'reason' => $checkResult->reason,
            ]);

            return;
        }

        $this->completionHelper->complete($resolved);
    }

    private function firstPaidAttempt(OrderInterface $order): ?AlmaPaymentReference
    {
        $paid = array_filter(
            $this->referenceRepository->findByOrder($order),
            static fn (AlmaPaymentReference $reference): bool => $reference->isPaid(),
        );
        usort($paid, static fn (AlmaPaymentReference $a, AlmaPaymentReference $b): int => $a->getPaidAt() <=> $b->getPaidAt());

        return $paid[0] ?? null;
    }
}
