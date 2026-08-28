<?php

declare(strict_types=1);

namespace Alma\Sylius\Eligibility;

use Alma\Sylius\Api\FeePlanAdapter;
use Alma\Sylius\Api\FeePlanAdapterCollectionBuilder;
use Alma\Sylius\Entity\AlmaConfiguration;

/**
 * Local pre-filter of fee plans for a given cart total.
 *
 * Source of truth = the merged view of `fee_plans` (API-provided, refreshed at
 * page load) and `fee_plan_overrides` (local merchant `enabled` + min/max
 * overrides), as materialized by {@see FeePlanAdapter}. A plan is a candidate
 * for downstream eligibility iff:
 *   - it is `allowed` by Alma (API), AND
 *   - it is locally `enabled` by the merchant (default false, opt-in), AND
 *   - the cart total falls within its effective `[min; max]` range, with
 *     override values taking priority over API values.
 *
 * Consumers (cf. capability checkout, "Local pre-filtering of fee plans") MUST
 * use the result of this provider as the `queries` payload of
 * `EligibilityEndpoint::getEligibilityList()`.
 */
final class FeePlanCandidateProvider
{
    public function __construct(
        private readonly FeePlanAdapterCollectionBuilder $collectionBuilder,
    ) {
    }

    /**
     * @return array<string, FeePlanAdapter> keyed by functional plan key
     */
    public function findCandidates(AlmaConfiguration $configuration, int $purchaseAmountInCents): array
    {
        $adapters = $this->collectionBuilder->build($configuration);

        $candidates = [];
        foreach ($adapters as $planKey => $adapter) {
            if ($adapter->isAvailable() && $adapter->isEligible($purchaseAmountInCents)) {
                $candidates[$planKey] = $adapter;
            }
        }

        return $candidates;
    }
}
