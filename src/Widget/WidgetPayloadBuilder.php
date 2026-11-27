<?php

declare(strict_types=1);

namespace Alma\Sylius\Widget;

use Alma\Sylius\Api\FeePlanAdapterCollectionBuilder;
use Alma\Sylius\Entity\AlmaConfiguration;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Payload parts shared by the product and cart widgets: the merchant-enabled
 * fee plans with their bounds, and the locale in the widget's format.
 */
final class WidgetPayloadBuilder
{
    public function __construct(
        private readonly FeePlanAdapterCollectionBuilder $feePlanCollectionBuilder,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    /**
     * @return list<array<string, int>>
     */
    public function plans(AlmaConfiguration $configuration): array
    {
        $plans = [];
        foreach ($this->feePlanCollectionBuilder->build($configuration) as $adapter) {
            if (!$adapter->isAvailable()) {
                continue;
            }
            $plan = [
                'installmentsCount' => $adapter->getInstallmentsCount(),
                'minAmount' => $adapter->getMinPurchaseAmount(),
                'maxAmount' => $adapter->getMaxPurchaseAmount(),
            ];
            $deferredDays = $adapter->getDeferredDays();
            if ($deferredDays > 0) {
                $plan['deferredDays'] = $deferredDays;
            }
            $deferredMonths = $adapter->getDeferredMonths();
            if ($deferredMonths > 0) {
                $plan['deferredMonths'] = $deferredMonths;
            }
            $plans[] = $plan;
        }

        return $plans;
    }

    /**
     * Sylius exposes locales as `xx_XX` (e.g. `fr_FR`). The Alma widget
     * accepts `xx` or `xx-XX` (hyphen). Convert when the shape matches,
     * otherwise omit and let the widget fall back to its default.
     */
    public function locale(): ?string
    {
        $locale = $this->localeContext->getLocaleCode();
        if ($locale === '') {
            return null;
        }
        if (preg_match('/^([a-z]{2})_([A-Z]{2})$/', $locale, $m) === 1) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/^[a-z]{2}$/', $locale) === 1) {
            return $locale;
        }

        return null;
    }
}
