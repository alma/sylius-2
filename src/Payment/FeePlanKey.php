<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

/**
 * Functional fee plan key `<kind>_<installments>_<deferred_days>_<deferred_months>`
 * (e.g. `general_3_0_0`), split into the plan parameters Alma expects at
 * payment creation and in the In-Page frame.
 */
final class FeePlanKey
{
    private function __construct(
        public readonly string $value,
        public readonly int $installmentsCount,
        public readonly int $deferredDays,
        public readonly int $deferredMonths,
    ) {
    }

    public static function ofGeneralPlan(int $installmentsCount, int $deferredDays, int $deferredMonths): self
    {
        return new self(
            sprintf('general_%d_%d_%d', $installmentsCount, $deferredDays, $deferredMonths),
            $installmentsCount,
            $deferredDays,
            $deferredMonths,
        );
    }

    public static function parse(string $planKey): self
    {
        $parts = explode('_', $planKey);
        $count = count($parts);
        if ($count < 4) {
            throw new \InvalidArgumentException(sprintf('Invalid fee plan key "%s".', $planKey));
        }

        return new self(
            $planKey,
            (int) $parts[$count - 3],
            (int) $parts[$count - 2],
            (int) $parts[$count - 1],
        );
    }
}
