<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Sylius\Component\Payment\PaymentTransitions;

/**
 * Idempotent transition of the Sylius Payment to `cancelled` after a customer
 * cancel on Alma's hosted page. Mirrors {@see AlmaPaymentCompletionHelper}: the
 * `cancel` transition is guarded by `StateMachineInterface::can()` (via the
 * abstract base), so replays (browser back, double-tap on the cancel button,
 * concurrent IPN) become silent no-ops.
 *
 * Scope is restricted to the Sylius Payment entity: the helper MUST NOT touch
 * the Order or its checkout state — the order remains alive so the customer
 * can retry payment from `sylius_shop_order_show`, aligning with the Sylius
 * native pattern (see ResolveNextRouteAction in PayumBundle).
 */
class AlmaPaymentCancellationHelper extends AbstractPaymentTransitionHelper
{
    public function cancel(ResolvedPayment $resolved): bool
    {
        return $this->doApply($resolved);
    }

    protected function getTransition(): string
    {
        return PaymentTransitions::TRANSITION_CANCEL;
    }

    protected function getSkippedLogKey(): string
    {
        return 'alma.payment.cancel_skipped';
    }

    protected function getAppliedLogKey(): string
    {
        return 'alma.payment.cancelled';
    }
}
