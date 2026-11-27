<?php

declare(strict_types=1);

namespace Alma\Sylius\InPage;

use Alma\Sylius\Payment\AlmaPaymentDetailsKeys;
use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

class OrderAlmaPaymentsProvider
{
    private const PAID_PAYMENT_STATES = [PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_REFUNDED];

    public function __construct(
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
    ) {
    }

    /**
     * @return list<array{almaPaymentId: string, planKey: ?string, mode: string, paid: bool, paidTheOrder: bool}>
     */
    public function forOrder(OrderInterface $order): array
    {
        $rows = [];
        foreach ($this->referenceRepository->findByOrder($order) as $reference) {
            // Once another gateway's payment failed, Sylius gave the order a new
            // payment: that one, not the attempt's own, may be the one Alma paid.
            $candidates = [$reference->getSyliusPayment(), ...$order->getPayments()->toArray()];
            $paidTheOrder = false;
            foreach ($candidates as $payment) {
                $paidTheOrder = $paidTheOrder || $this->isPaidByAttempt($payment, $reference->getAlmaPaymentId());
            }

            $rows[] = [
                'almaPaymentId' => $reference->getAlmaPaymentId(),
                'planKey' => $reference->getFeePlanKey(),
                'mode' => $reference->getAlmaMode(),
                'paid' => $reference->isPaid() || $paidTheOrder,
                'paidTheOrder' => $paidTheOrder,
            ];
        }

        return $rows;
    }

    private function isPaidByAttempt(PaymentInterface $payment, string $almaPaymentId): bool
    {
        return \in_array($payment->getState(), self::PAID_PAYMENT_STATES, true)
            && $payment->getMethod()?->getGatewayConfig()?->getFactoryName() === AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME
            && ($payment->getDetails()[AlmaPaymentDetailsKeys::PAYMENT_ID] ?? null) === $almaPaymentId;
    }
}
