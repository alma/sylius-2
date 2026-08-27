<?php

declare(strict_types=1);

namespace Alma\Sylius\Eligibility;

use Alma\Client\Domain\Entity\Eligibility as AlmaResponseEligibility;
use Alma\Sylius\Api\FeePlanAdapter;

/**
 * Plugin-side representation of a single Alma eligibility entry.
 *
 * Combines:
 *   - the API response data for this plan (isEligible, installments schedule,
 *     customer fees, APR, ineligibility reasons), accessible via remote-* getters;
 *   - the local merged view of the same plan (label, effective range, enabled
 *     flag), accessible via getLocal().
 *
 * Downstream consumers (radio rendering, payment creation, refund) MUST read
 * this adapter rather than coupling to the PHP client's response shape. This
 * isolates the plugin from PHP client version changes and provides a single
 * source of truth that already merges API and local data.
 */
class AlmaEligibility
{
    public function __construct(
        private readonly string $planKey,
        private readonly FeePlanAdapter $local,
        private readonly AlmaResponseEligibility $remote,
    ) {
    }

    public function getPlanKey(): string
    {
        return $this->planKey;
    }

    public function getLocal(): FeePlanAdapter
    {
        return $this->local;
    }

    public function getLabel(): string
    {
        return $this->local->getLabel();
    }

    public function isEligible(): bool
    {
        return $this->remote->isEligible();
    }

    public function getInstallmentsCount(): int
    {
        return $this->remote->getInstallmentsCount();
    }

    public function getDeferredDays(): int
    {
        return $this->remote->getDeferredDays();
    }

    public function getDeferredMonths(): int
    {
        return $this->remote->getDeferredMonths();
    }

    public function getCustomerFee(): int
    {
        return $this->remote->getCustomerFee();
    }

    public function getCustomerTotalCostAmount(): int
    {
        return $this->remote->getCustomerTotalCostAmount();
    }

    public function getCustomerTotalCostBps(): int
    {
        return $this->remote->getCustomerTotalCostBps();
    }

    public function getAnnualInterestRate(): int
    {
        return $this->remote->getAnnualInterestRate();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentPlan(): array
    {
        return $this->remote->getPaymentPlan();
    }

    /**
     * @return array<string, mixed>
     */
    public function getReasons(): array
    {
        return $this->remote->getReasons();
    }
}
