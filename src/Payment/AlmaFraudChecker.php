<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Domain\Entity\Payment as AlmaPayment;

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

        // The resolver found the order through this pid, so this check cannot
        // fail on a resolved payment: the guard against another order's pid is
        // the resolver's `custom_data.order_id` cross-check.
        $storedPid = $resolved->reference !== null && $resolved->reference->getSyliusOrder() === $order
            ? $resolved->reference->getAlmaPaymentId()
            : $syliusPayment->getDetails()[AlmaPaymentDetailsKeys::PAYMENT_ID] ?? null;
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
