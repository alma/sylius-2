<?php

declare(strict_types=1);

namespace Alma\Sylius\ProductWidget;

use Alma\Sylius\Api\FeePlanAdapterCollectionBuilder;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Builds the configuration payload consumed by the Alma product-page widget.
 * The payload is rendered server-side as a `<script type="application/json">`
 * block and read by the bootstrap JS (`public/js/product-widget.js`).
 *
 * The unit price is the one of the variant currently selected on the product
 * page. Sylius 2.0 renders the product summary through a Symfony UX Live
 * Component whose Twig hook re-renders with the newly selected variant on
 * every change, so this payload is rebuilt server-side with the right price
 * on each morph. No client-side variant→price map is exposed: with Sylius'
 * "options match" selection method the browser only sees option codes, never
 * variant ids, so such a map could not even be keyed client-side.
 *
 * Returns `null` whenever the widget MUST NOT be rendered:
 *  - the merchant has not enabled the toggle (cf. spec Requirement « Widget is
 *    enabled or disabled via a single back-office toggle »),
 *  - the plugin has no persisted `merchant_id` (the configuration was never
 *    saved with a valid API key),
 *  - no variant is selected, or the selected variant has no price in the
 *    active channel.
 *
 * When returning `null` the Twig hook MUST emit nothing — no `<script>` tag,
 * no placeholder DOM (cf. Requirement « Widget is silent when disabled »).
 */
final class AlmaProductWidgetConfigProvider
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly FeePlanAdapterCollectionBuilder $feePlanCollectionBuilder,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(?ProductVariantInterface $variant): ?array
    {
        $configuration = $this->configurationResolver->getConfiguration();
        if (!$configuration->isProductWidgetEnabled()) {
            return null;
        }

        $merchantId = $configuration->getMerchantId();
        if ($merchantId === null || $merchantId === '') {
            return null;
        }

        if ($variant === null) {
            return null;
        }

        $channel = $this->channelContext->getChannel();
        $unitPrice = $this->resolveVariantPrice($variant, $channel);
        if ($unitPrice === null) {
            return null;
        }

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

        return [
            'merchantId' => $merchantId,
            'mode' => strtoupper($configuration->getApiMode()),
            'unitPrice' => $unitPrice,
            'currency' => method_exists($channel, 'getBaseCurrency') ? ($channel->getBaseCurrency()?->getCode() ?? 'EUR') : 'EUR',
            'locale' => $this->resolveLocale(),
            'plans' => $plans,
        ];
    }

    private function resolveVariantPrice(ProductVariantInterface $variant, object $channel): ?int
    {
        if (!method_exists($channel, 'getCode')) {
            return null;
        }
        $channelPricings = $variant->getChannelPricings();
        $pricing = $channelPricings->get($channel->getCode());
        if ($pricing === null) {
            return null;
        }
        $price = $pricing->getPrice();

        return is_int($price) ? $price : null;
    }

    /**
     * Sylius exposes locales as `xx_XX` (e.g. `fr_FR`). The Alma widget
     * accepts `xx` or `xx-XX` (hyphen). Convert when the shape matches,
     * otherwise omit and let the widget fall back to its default.
     */
    private function resolveLocale(): ?string
    {
        $locale = $this->localeContext->getLocaleCode();
        if (!is_string($locale) || $locale === '') {
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
