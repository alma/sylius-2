<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Domain\Entity\Payment as AlmaPayment;

/**
 * Performs the four anti-fraud checks mandated by the customer-return
 * Requirement "Anti-fraud checks before any state transition" before any
 * payment-related state transition. Reusable by both the return controller
 * (E2) and the IPN handler (F2/F4).
 *
 * Checks (short-circuit on first failure) :
 *  1. pid : the Alma payment id matches the one persisted on the order's Sylius
 *     Payment (defensive — the AlmaPaymentResolver also enforces this).
 *  2. amount : the Alma `purchase_amount` (cents) equals the Sylius order total.
 *  3. processing_status : maps to PHP client v3 `Payment::getProcessingStatus()`
 *     and MUST be in {authorized, captured}. Anything else (including null,
 *     awaiting_authorization, canceled, or unknown) → state_error mismatch.
 *  4. expiration : `expiredAt` is either null (no expiration set) or strictly
 *     in the future.
 */
class AlmaFraudChecker
{
    private const ACCEPTED_PROCESSING_STATUSES = [
        AlmaPayment::PROCESSING_STATUS_AUTHORIZED,
        AlmaPayment::PROCESSING_STATUS_CAPTURED,
    ];

    public function check(ResolvedPayment $resolved): AlmaFraudCheckResult
    {
        $almaPayment = $resolved->almaPayment;
        $order = $resolved->order;
        $syliusPayment = $resolved->syliusPayment;

        $storedPid = $syliusPayment->getDetails()[AlmaPaymentDetailsKeys::PAYMENT_ID] ?? null;
        if ($storedPid !== $almaPayment->getId()) {
            return AlmaFraudCheckResult::fail(AlmaFraudCheckResult::REASON_PID_MISMATCH);
        }

        if ($almaPayment->getPurchaseAmount() !== $order->getTotal()) {
            return AlmaFraudCheckResult::fail(AlmaFraudCheckResult::REASON_AMOUNT_MISMATCH);
        }

        $status = $almaPayment->getProcessingStatus();
        if (!in_array($status, self::ACCEPTED_PROCESSING_STATUSES, true)) {
            return AlmaFraudCheckResult::fail(AlmaFraudCheckResult::REASON_STATE_ERROR);
        }

        $expiredAt = $almaPayment->getExpiredAt();
        if ($expiredAt !== null && $expiredAt <= time()) {
            return AlmaFraudCheckResult::fail(AlmaFraudCheckResult::REASON_EXPIRED);
        }

        return AlmaFraudCheckResult::pass();
    }
}
