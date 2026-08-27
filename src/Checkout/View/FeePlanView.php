<?php

declare(strict_types=1);

namespace Alma\Sylius\Checkout\View;

final class FeePlanView
{
    /**
     * @param array<string, int>                                           $buttonLabelParams
     * @param list<array{isToday: bool, dueDate: int|null, amount: int}>   $scheduleRows
     */
    public function __construct(
        public readonly string $planKey,
        public readonly string $buttonLabelKey,
        public readonly array $buttonLabelParams,
        public readonly array $scheduleRows,
        public readonly int $total,
        public readonly int $customerFee,
    ) {
    }
}
