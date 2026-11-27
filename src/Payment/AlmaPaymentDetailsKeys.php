<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

/**
 * Constants for the keys persisted by the plugin on the Sylius Payment's
 * `details` JSON column. Centralizing them avoids typos and lets the IDE
 * autocomplete every read/write of the Payment-side Alma metadata.
 *
 * These keys are the **canonical contract** between :
 *  - `AlmaCaptureAction` (writes at payment creation: id, url, mode, fee plan key)
 *  - `AlmaPaymentResolver` (reads at return / IPN / refund — though as of the
 *    indexed-reference refactor, the resolver only reads PAYMENT_ID to check
 *    payment ownership)
 *  - `AlmaFraudFlagger` (writes FRAUD_REASON on mismatch)
 *  - `AlmaRefundHelper` (writes REFUNDS and AMOUNT_REFUNDED on successful refund)
 *  - `AlmaPaymentDetailsType` (uses FEE_PLAN_KEY as the form field name AND
 *    the persisted key — same string, single source of truth)
 *
 * NOT to be confused with :
 *  - the `alma_payment_reference` table columns (different storage, raw SQL),
 *  - log context keys (semantically a different concept — they describe what
 *    a logged value represents, not where it lives in the payment).
 */
final class AlmaPaymentDetailsKeys
{
    public const PAYMENT_ID = 'alma_payment_id';
    public const PAYMENT_URL = 'alma_payment_url';
    public const MODE = 'alma_mode';
    public const FEE_PLAN_KEY = 'alma_fee_plan_key';
    /** list<array{amount: int, comment: string, refunded_at: string}>, one entry per Alma refund */
    public const REFUNDS = 'alma_refunds';
    /** Cumulative amount refunded on the Alma payment, in cents, as Alma last returned it */
    public const AMOUNT_REFUNDED = 'alma_amount_refunded';
    public const FRAUD_REASON = 'alma_fraud_reason';

    private function __construct()
    {
    }
}
