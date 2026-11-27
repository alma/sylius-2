<?php

declare(strict_types=1);

namespace Alma\Sylius\CartWidget;

use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Exclusion\ExcludedTaxonMatcher;
use Alma\Sylius\Exclusion\ExclusionMessageResolver;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Resolver\AlmaStorefrontVisibility;
use Alma\Sylius\Widget\WidgetPayloadBuilder;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;

/**
 * Builds what the cart page widget renders at one of the
 * {@see CartWidgetPosition}s: the payload read by `public/js/cart-widget.js`,
 * or the exclusion message shown in its place.
 *
 * The cart is read from the cart context, not from the Twig hook context:
 * the cart form is a Live Component whose re-renders do not carry the hook
 * context back.
 *
 * Returns `null` when nothing may be rendered at that position, assets
 * included: toggle off, no persisted `merchant_id`, Alma hidden from this
 * visitor in Test mode, another position picked,
 * no cart or an empty one. Otherwise the widget assets stay on the page, so
 * the widget comes back without a reload once the cart is no longer excluded.
 */
final class AlmaCartWidgetConfigProvider
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly CartContextInterface $cartContext,
        private readonly WidgetPayloadBuilder $payloadBuilder,
        private readonly LocaleContextInterface $localeContext,
        private readonly ExcludedTaxonMatcher $excludedTaxonMatcher,
        private readonly ExclusionMessageResolver $exclusionMessageResolver,
        private readonly AlmaStorefrontVisibility $storefrontVisibility,
    ) {
    }

    /**
     * An excluded cart has no payload; its message is `null` when the merchant
     * hides the widget for ineligible carts.
     *
     * @return array{config: array<string, mixed>|null, exclusionMessage: string|null}|null
     */
    public function render(string $position): ?array
    {
        $cart = $this->currentCart();
        if ($cart === null || $cart->isEmpty()) {
            return null;
        }

        $configuration = $this->configurationResolver->getConfiguration($cart->getChannel());
        $merchantId = $configuration->getMerchantId();
        if (!$configuration->isCartWidgetEnabled() || $merchantId === null || $merchantId === '') {
            return null;
        }

        if ($this->storefrontVisibility->isHidden()) {
            return null;
        }

        if ($position !== $configuration->getCartWidgetPosition()) {
            return null;
        }

        if ($this->excludedTaxonMatcher->isOrderExcluded($cart, $configuration->getExcludedTaxonCodes())) {
            return [
                'config' => null,
                'exclusionMessage' => $configuration->isCartWidgetShownIfNotEligible()
                    ? $this->exclusionMessageResolver->resolve(
                        $configuration->getExclusionMessages() ?? [],
                        $this->localeContext->getLocaleCode(),
                    )
                    : null,
            ];
        }

        return [
            'config' => $this->payload($configuration, $merchantId, $cart),
            'exclusionMessage' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AlmaConfiguration $configuration, string $merchantId, OrderInterface $cart): array
    {
        return [
            'merchantId' => $merchantId,
            'mode' => strtoupper($configuration->getApiMode()),
            'amount' => $cart->getTotal(),
            'currency' => $cart->getCurrencyCode() ?? 'EUR',
            'locale' => $this->payloadBuilder->locale(),
            'plans' => $this->payloadBuilder->plans($configuration),
            'monochrome' => $configuration->isCartWidgetMonochrome(),
            'hideBorder' => $configuration->isCartWidgetHideBorder(),
            'transitionDelay' => $configuration->getCartWidgetTransitionDelay(),
            'hideIfNotEligible' => !$configuration->isCartWidgetShownIfNotEligible(),
        ];
    }

    private function currentCart(): ?OrderInterface
    {
        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return null;
        }

        return $cart instanceof OrderInterface ? $cart : null;
    }
}
