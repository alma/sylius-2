<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Sylius\Entity\AlmaConfiguration;

final class FeePlanAdapterCollectionBuilder
{
    /**
     * Builds a map of plan_key → FeePlanAdapter from the persisted singleton
     * configuration. Iteration order follows the persisted fee_plans JSON.
     *
     * @return array<string, FeePlanAdapter>
     */
    public function build(AlmaConfiguration $configuration): array
    {
        $apiPlans = $configuration->getFeePlans() ?? [];
        $overrides = $configuration->getFeePlanOverrides() ?? [];

        $result = [];
        foreach ($apiPlans as $planKey => $apiData) {
            $result[$planKey] = new FeePlanAdapter($apiData, $overrides[$planKey] ?? []);
        }

        return $result;
    }
}
