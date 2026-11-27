<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * An order is already paid once its payment state is paid, partially paid,
 * refunded or partially refunded (cf. specs-sylius/in-page, « Already paid »):
 * no new Alma payment is created for it and no transition is applied when
 * another of its Alma payments is reported paid.
 */
final class AlreadyPaidOrder
{
    private const PAYMENT_STATES = [
        OrderPaymentStates::STATE_PAID,
        OrderPaymentStates::STATE_PARTIALLY_PAID,
        OrderPaymentStates::STATE_REFUNDED,
        OrderPaymentStates::STATE_PARTIALLY_REFUNDED,
    ];

    private function __construct()
    {
    }

    public static function is(OrderInterface $order): bool
    {
        return \in_array($order->getPaymentState(), self::PAYMENT_STATES, true);
    }
}
