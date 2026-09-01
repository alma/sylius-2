<?php

declare(strict_types=1);

namespace Alma\Sylius\Checkout;

use Alma\Sylius\Eligibility\AlmaEligibility;

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
        $installments = $eligibility->getInstallmentsCount();

        if ($installments > 4) {
            return self::Credit;
        }

        if ($installments >= 2) {
            return self::Installments;
        }

        if ($eligibility->getDeferredDays() > 0 || $eligibility->getDeferredMonths() > 0) {
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
}
