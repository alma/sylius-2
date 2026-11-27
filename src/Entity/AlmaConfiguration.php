<?php

declare(strict_types=1);

namespace Alma\Sylius\Entity;

use Alma\Sylius\CartWidget\CartWidgetPosition;

/**
 * In-memory representation of the Alma configuration values exposed to the
 * rest of the plugin via {@see \Alma\Sylius\Resolver\AlmaPaymentMethodResolver::getConfiguration()}.
 *
 * Historically a Doctrine entity backed by a singleton `alma_configuration`
 * table; since the move-config-to-payment-method change the data lives in the
 * `config` JSON of the unique Alma {@see \Sylius\Component\Payment\Model\PaymentMethodInterface}'s
 * gatewayConfig (Sylius-native pattern, see specs-sylius/module-configuration).
 *
 * This class is no longer persisted by Doctrine. The resolver builds it from
 * the JSON config on demand, downstream consumers keep the same getter API.
 */
class AlmaConfiguration
{
    public const MODE_LIVE = 'live';
    public const MODE_TEST = 'test';
    public const MODES = [self::MODE_LIVE, self::MODE_TEST];

    /** Delay between two plans in the widget animation, in ms; -1 stops it. */
    public const DEFAULT_CART_WIDGET_TRANSITION_DELAY = 5500;
    public const MIN_CART_WIDGET_TRANSITION_DELAY = -1;

    private ?string $apiKeyLive = null;
    private ?string $apiKeyTest = null;
    private string $apiMode = self::MODE_TEST;
    private ?string $merchantId = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $feePlans = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $feePlanOverrides = null;

    private bool $productWidgetEnabled = true;

    private bool $inPageEnabled = false;

    private bool $unifiedPaymentButtonEnabled = false;

    private bool $cartWidgetEnabled = true;
    private string $cartWidgetPosition = CartWidgetPosition::DEFAULT;
    private bool $cartWidgetMonochrome = true;
    private bool $cartWidgetHideBorder = false;
    private int $cartWidgetTransitionDelay = self::DEFAULT_CART_WIDGET_TRANSITION_DELAY;
    private bool $cartWidgetShownIfNotEligible = true;

    /** @var array<string, array<string, array<string, mixed>>>|null */
    private ?array $displayTexts = null;

    /** @var list<string> */
    private array $excludedTaxonCodes = [];

    /** @var array<string, string>|null */
    private ?array $exclusionMessages = null;

    public function getApiKeyLive(): ?string
    {
        return $this->apiKeyLive;
    }

    public function setApiKeyLive(?string $apiKeyLive): self
    {
        $this->apiKeyLive = $apiKeyLive;

        return $this;
    }

    public function getApiKeyTest(): ?string
    {
        return $this->apiKeyTest;
    }

    public function setApiKeyTest(?string $apiKeyTest): self
    {
        $this->apiKeyTest = $apiKeyTest;

        return $this;
    }

    public function getApiMode(): string
    {
        return $this->apiMode;
    }

    public function setApiMode(string $apiMode): self
    {
        $this->apiMode = $apiMode;

        return $this;
    }

    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    public function setMerchantId(?string $merchantId): self
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getFeePlans(): ?array
    {
        return $this->feePlans;
    }

    /**
     * @param array<string, array<string, mixed>>|null $feePlans
     */
    public function setFeePlans(?array $feePlans): self
    {
        $this->feePlans = $feePlans;

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getFeePlanOverrides(): ?array
    {
        return $this->feePlanOverrides;
    }

    /**
     * @param array<string, array<string, mixed>>|null $feePlanOverrides
     */
    public function setFeePlanOverrides(?array $feePlanOverrides): self
    {
        $this->feePlanOverrides = $feePlanOverrides;

        return $this;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    public function getDisplayTexts(): ?array
    {
        return $this->displayTexts;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>>|null $displayTexts
     */
    public function setDisplayTexts(?array $displayTexts): self
    {
        $this->displayTexts = $displayTexts;

        return $this;
    }

    public function isProductWidgetEnabled(): bool
    {
        return $this->productWidgetEnabled;
    }

    public function setProductWidgetEnabled(bool $productWidgetEnabled): self
    {
        $this->productWidgetEnabled = $productWidgetEnabled;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getExcludedTaxonCodes(): array
    {
        return $this->excludedTaxonCodes;
    }

    /**
     * @param list<string> $excludedTaxonCodes
     */
    public function setExcludedTaxonCodes(array $excludedTaxonCodes): self
    {
        $this->excludedTaxonCodes = $excludedTaxonCodes;

        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getExclusionMessages(): ?array
    {
        return $this->exclusionMessages;
    }

    /**
     * @param array<string, string>|null $exclusionMessages
     */
    public function setExclusionMessages(?array $exclusionMessages): self
    {
        $this->exclusionMessages = $exclusionMessages;

        return $this;
    }

    public function isInPageEnabled(): bool
    {
        return $this->inPageEnabled;
    }

    public function setInPageEnabled(bool $inPageEnabled): self
    {
        $this->inPageEnabled = $inPageEnabled;

        return $this;
    }

    public function isUnifiedPaymentButtonEnabled(): bool
    {
        return $this->unifiedPaymentButtonEnabled;
    }

    public function setUnifiedPaymentButtonEnabled(bool $unifiedPaymentButtonEnabled): self
    {
        $this->unifiedPaymentButtonEnabled = $unifiedPaymentButtonEnabled;

        return $this;
    }

    public function isCartWidgetEnabled(): bool
    {
        return $this->cartWidgetEnabled;
    }

    public function setCartWidgetEnabled(bool $cartWidgetEnabled): self
    {
        $this->cartWidgetEnabled = $cartWidgetEnabled;

        return $this;
    }

    public function getCartWidgetPosition(): string
    {
        return $this->cartWidgetPosition;
    }

    public function setCartWidgetPosition(string $cartWidgetPosition): self
    {
        $this->cartWidgetPosition = $cartWidgetPosition;

        return $this;
    }

    public function isCartWidgetMonochrome(): bool
    {
        return $this->cartWidgetMonochrome;
    }

    public function setCartWidgetMonochrome(bool $cartWidgetMonochrome): self
    {
        $this->cartWidgetMonochrome = $cartWidgetMonochrome;

        return $this;
    }

    public function isCartWidgetHideBorder(): bool
    {
        return $this->cartWidgetHideBorder;
    }

    public function setCartWidgetHideBorder(bool $cartWidgetHideBorder): self
    {
        $this->cartWidgetHideBorder = $cartWidgetHideBorder;

        return $this;
    }

    public function getCartWidgetTransitionDelay(): int
    {
        return $this->cartWidgetTransitionDelay;
    }

    public function setCartWidgetTransitionDelay(int $cartWidgetTransitionDelay): self
    {
        $this->cartWidgetTransitionDelay = $cartWidgetTransitionDelay;

        return $this;
    }

    public function isCartWidgetShownIfNotEligible(): bool
    {
        return $this->cartWidgetShownIfNotEligible;
    }

    public function setCartWidgetShownIfNotEligible(bool $cartWidgetShownIfNotEligible): self
    {
        $this->cartWidgetShownIfNotEligible = $cartWidgetShownIfNotEligible;

        return $this;
    }
}
