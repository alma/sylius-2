<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Sylius\Component\Payment\PaymentTransitions;

/**
 * Idempotent transition of the Sylius Payment from `new` to `completed`.
 *
 * Shared between the customer return route (E1/E2) and the IPN handler (F3).
 * The helper applies only the `payment.complete` transition ; everything
 * downstream (order_payment → paid, inventory release, order state resolution,
 * invoice/email if the merchant has the relevant plugins installed) cascades
 * natively through the Sylius 2.0 workflow listeners.
 *
 * Idempotence is guaranteed by the abstract base via `StateMachineInterface::can()`
 * — replaying `complete()` on a payment already `completed` is a silent no-op.
 */
class AlmaPaymentCompletionHelper extends AbstractPaymentTransitionHelper
{
    public function complete(ResolvedPayment $resolved): bool
    {
        return $this->doApply($resolved);
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
}
