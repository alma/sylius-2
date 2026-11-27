<?php

declare(strict_types=1);

namespace Alma\Sylius\ProductWidget;

use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Exclusion\ExcludedTaxonMatcher;
use Alma\Sylius\Exclusion\ExclusionMessageResolver;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Resolver\AlmaStorefrontVisibility;
use Alma\Sylius\Widget\WidgetPayloadBuilder;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ProductInterface;
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
 *  - Alma is hidden from this visitor in Test mode,
 *  - the plugin has no persisted `merchant_id` (the configuration was never
 *    saved with a valid API key),
 *  - no variant is selected, or the selected variant has no price in the
 *    active channel,
 *  - the product belongs to an excluded category: {@see exclusionMessage()}
 *    then provides the message rendered in place of the widget.
 *
 * When returning `null` the Twig hook MUST emit nothing — no `<script>` tag,
 * no placeholder DOM (cf. Requirement « Widget is silent when disabled »).
 */
final class AlmaProductWidgetConfigProvider
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly WidgetPayloadBuilder $payloadBuilder,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly ExcludedTaxonMatcher $excludedTaxonMatcher,
        private readonly ExclusionMessageResolver $exclusionMessageResolver,
        private readonly AlmaStorefrontVisibility $storefrontVisibility,
    ) {
    }

    /**
     * The message shown in place of the widget for a product of an excluded
     * category, or `null` when the widget itself would not be rendered.
     */
    public function exclusionMessage(?ProductVariantInterface $variant): ?string
    {
        $configuration = $this->configurationResolver->getConfiguration($this->channelContext->getChannel());
        if ($variant === null || !$this->isWidgetActive($configuration) || !$this->isExcluded($variant, $configuration)) {
            return null;
        }

        return $this->exclusionMessageResolver->resolve(
            $configuration->getExclusionMessages() ?? [],
            $this->localeContext->getLocaleCode(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(?ProductVariantInterface $variant): ?array
    {
        $channel = $this->channelContext->getChannel();
        $configuration = $this->configurationResolver->getConfiguration($channel);
        if (!$this->isWidgetActive($configuration)) {
            return null;
        }

        if ($variant === null || $this->isExcluded($variant, $configuration)) {
            return null;
        }

        $merchantId = (string) $configuration->getMerchantId();

        $unitPrice = $this->resolveVariantPrice($variant, $channel);
        if ($unitPrice === null) {
            return null;
        }

        return [
            'merchantId' => $merchantId,
            'mode' => strtoupper($configuration->getApiMode()),
            'unitPrice' => $unitPrice,
            'currency' => method_exists($channel, 'getBaseCurrency') ? ($channel->getBaseCurrency()?->getCode() ?? 'EUR') : 'EUR',
            'locale' => $this->payloadBuilder->locale(),
            'plans' => $this->payloadBuilder->plans($configuration),
        ];
    }

    private function isWidgetActive(AlmaConfiguration $configuration): bool
    {
        $merchantId = $configuration->getMerchantId();

        return $configuration->isProductWidgetEnabled()
            && $merchantId !== null
            && $merchantId !== ''
            && !$this->storefrontVisibility->isHidden();
    }

    private function isExcluded(ProductVariantInterface $variant, AlmaConfiguration $configuration): bool
    {
        $product = $variant->getProduct();

        return $product instanceof ProductInterface
            && $this->excludedTaxonMatcher->isProductExcluded($product, $configuration->getExcludedTaxonCodes());
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
}
