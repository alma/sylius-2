<?php

declare(strict_types=1);

namespace Alma\Sylius\Checkout\View;

use Alma\Sylius\Checkout\PlanGroup;

final class FeePlanGroupView
{
    /**
     * @param list<FeePlanView> $plans
     */
    public function __construct(
        public readonly PlanGroup $group,
        public readonly array $plans,
    ) {
    }
}
