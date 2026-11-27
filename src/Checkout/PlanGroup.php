<?php

declare(strict_types=1);

namespace Alma\Sylius\Checkout;

use Alma\Sylius\Eligibility\AlmaEligibility;
use Alma\Sylius\Payment\FeePlanKey;

// PHPCompatibility 9.3.5 predates PHP 8.1 enums and false-positives on $this
// inside enum methods.
// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts
enum PlanGroup: string
{
    case PayNow = 'pay_now';
    case Installments = 'installments';
    case Deferred = 'deferred';
    case Credit = 'credit';

    public static function classify(AlmaEligibility $eligibility): self
    {
        return self::fromPlanParameters(
            $eligibility->getInstallmentsCount(),
            $eligibility->getDeferredDays(),
            $eligibility->getDeferredMonths(),
        );
    }

    public static function fromPlanKey(string $planKey): self
    {
        $plan = FeePlanKey::parse($planKey);

        return self::fromPlanParameters($plan->installmentsCount, $plan->deferredDays, $plan->deferredMonths);
    }

    private static function fromPlanParameters(int $installments, int $deferredDays, int $deferredMonths): self
    {
        if ($installments > 4) {
            return self::Credit;
        }

        if ($installments >= 2) {
            return self::Installments;
        }

        if ($deferredDays > 0 || $deferredMonths > 0) {
            return self::Deferred;
        }

        return self::PayNow;
    }

    public function titleKey(): string
    {
        return sprintf('alma_sylius.checkout.group.%s.title', $this->value);
    }

    public function subtitleKey(): string
    {
        return sprintf('alma_sylius.checkout.group.%s.subtitle', $this->value);
    }

    public function showsButtons(): bool
    {
        return self::PayNow !== $this;
    }

    public function showsSchedule(): bool
    {
        return self::Installments === $this || self::Deferred === $this;
    }

    public function showsFeeLine(): bool
    {
        return self::PayNow !== $this;
    }

    /**
     * In the In-Page flow the Alma frame replaces the schedule and fee line.
     */
    public function showsInPageFrame(): bool
    {
        return self::PayNow !== $this;
    }
}
