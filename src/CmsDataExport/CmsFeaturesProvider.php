<?php

declare(strict_types=1);

namespace Alma\Sylius\CmsDataExport;

use Alma\Client\Application\DTO\MerchantData\CmsFeaturesDto;
use Alma\Sylius\Api\FeePlanAdapterCollectionBuilder;
use Alma\Sylius\Entity\AlmaConfiguration;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Locale\Provider\LocaleProviderInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

final class CmsFeaturesProvider
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param ChannelRepositoryInterface<ChannelInterface>             $channelRepository
     * @param TaxonRepositoryInterface<TaxonInterface>                 $taxonRepository
     */
    public function __construct(
        private readonly FeePlanAdapterCollectionBuilder $feePlanAdapterCollectionBuilder,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly LocaleProviderInterface $localeProvider,
    ) {
    }

    public function provide(PaymentMethodInterface $paymentMethod, AlmaConfiguration $configuration): CmsFeaturesDto
    {
        $sortedPaymentMethods = array_values(
            $this->paymentMethodRepository->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
        );

        return new CmsFeaturesDto([
            'alma_enabled' => $paymentMethod->isEnabled(),
            'widget_product_activated' => $configuration->isProductWidgetEnabled(),
            'widget_cart_activated' => $configuration->isCartWidgetEnabled(),
            'in_page_activated' => $configuration->isInPageEnabled(),
            'used_fee_plans' => $this->usedFeePlans($configuration),
            'excluded_categories' => $this->excludedCategories($paymentMethod, $configuration),
            'payment_method_position' => $this->positionOf($paymentMethod, $sortedPaymentMethods),
            'payment_methods_list' => $this->enabledPaymentMethods($paymentMethod, $sortedPaymentMethods),
            'is_multisite' => \count($this->channelRepository->findBy(['enabled' => true])) > 1,
        ]);
    }

    /**
     * @return array<string, array{enabled: true, min_amount: int, max_amount: int}>|null
     */
    private function usedFeePlans(AlmaConfiguration $configuration): ?array
    {
        $usedFeePlans = [];
        foreach ($this->feePlanAdapterCollectionBuilder->build($configuration) as $planKey => $feePlan) {
            if (!$feePlan->isAvailable()) {
                continue;
            }
            $usedFeePlans[$planKey] = [
                'enabled' => true,
                'min_amount' => $feePlan->getMinPurchaseAmount(),
                'max_amount' => $feePlan->getMaxPurchaseAmount(),
            ];
        }

        return $usedFeePlans === [] ? null : $usedFeePlans;
    }

    /**
     * @return list<string>|null
     */
    private function excludedCategories(PaymentMethodInterface $paymentMethod, AlmaConfiguration $configuration): ?array
    {
        $excludedTaxonCodes = $configuration->getExcludedTaxonCodes();
        if ($excludedTaxonCodes === []) {
            return null;
        }

        $localeCode = $this->defaultLocaleCode($paymentMethod);

        $names = [];
        foreach ($this->taxonRepository->findBy(['code' => $excludedTaxonCodes]) as $taxon) {
            $names[] = $taxon->getTranslation($localeCode)->getName() ?? (string) $taxon->getCode();
        }

        return $names === [] ? null : $names;
    }

    /**
     * @param list<PaymentMethodInterface> $sortedPaymentMethods
     */
    private function positionOf(PaymentMethodInterface $paymentMethod, array $sortedPaymentMethods): ?int
    {
        $index = array_search($paymentMethod, $sortedPaymentMethods, true);

        return $index === false ? null : $index + 1;
    }

    /**
     * @param list<PaymentMethodInterface> $sortedPaymentMethods
     *
     * @return list<array{name: string, position: int}>|null
     */
    private function enabledPaymentMethods(PaymentMethodInterface $almaPaymentMethod, array $sortedPaymentMethods): ?array
    {
        $localeCode = $this->defaultLocaleCode($almaPaymentMethod);

        $list = [];
        foreach ($sortedPaymentMethods as $index => $paymentMethod) {
            if (!$paymentMethod->isEnabled()) {
                continue;
            }
            $list[] = [
                'name' => $paymentMethod->getTranslation($localeCode)->getName() ?? (string) $paymentMethod->getCode(),
                'position' => $index + 1,
            ];
        }

        return $list === [] ? null : $list;
    }

    private function defaultLocaleCode(PaymentMethodInterface $paymentMethod): string
    {
        return AlmaChannels::primaryOf($paymentMethod)?->getDefaultLocale()?->getCode()
            ?? $this->localeProvider->getDefaultLocaleCode();
    }
}
