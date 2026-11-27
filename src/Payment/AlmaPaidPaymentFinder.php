<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

class AlmaPaidPaymentFinder
{
    private const PAID_STATES = [PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_REFUNDED];

    public function find(OrderInterface $order): ?PaymentInterface
    {
        foreach ($order->getPayments() as $payment) {
            if (
                \in_array($payment->getState(), self::PAID_STATES, true)
                && $payment->getMethod()?->getGatewayConfig()?->getFactoryName() === AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME
                && \is_string($payment->getDetails()[AlmaPaymentDetailsKeys::PAYMENT_ID] ?? null)
            ) {
                return $payment;
            }
        }

        return null;
    }
}
