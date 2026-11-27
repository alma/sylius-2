<?php

declare(strict_types=1);

namespace Alma\Sylius\Resolver;

use Alma\Sylius\CartWidget\CartWidgetPosition;
use Alma\Sylius\Entity\AlmaConfiguration;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface as BasePaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Single access point to the operational Alma configuration of a channel.
 *
 * Each Alma PaymentMethod carries, in the `config` JSON of its gatewayConfig,
 * the configuration of the channels it is assigned to; a channel has at most
 * one (see specs-sylius/module-configuration). This resolver exposes it as an
 * in-memory {@see AlmaConfiguration} so downstream code does not couple to
 * the Sylius storage shape.
 */
class AlmaPaymentMethodResolver implements ResetInterface
{
    public const GATEWAY_FACTORY_NAME = 'alma';

    /** @var array<string, AlmaConfiguration> */
    private array $configurations = [];

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function isAlma(BasePaymentMethodInterface $paymentMethod): bool
    {
        return $paymentMethod->getGatewayConfig()?->getFactoryName() === self::GATEWAY_FACTORY_NAME;
    }

    /**
     * Memoized per channel: a checkout render reads the configuration from
     * several consumers.
     */
    public function getConfiguration(?ChannelInterface $channel): AlmaConfiguration
    {
        $code = $channel?->getCode();
        if ($channel === null || $code === null) {
            return new AlmaConfiguration();
        }

        return $this->configurations[$code] ??= $this->buildConfiguration(
            $this->getAlmaPaymentMethod($channel)?->getGatewayConfig()?->getConfig() ?? [],
        );
    }

    public function getConfigurationOf(PaymentMethodInterface $paymentMethod): AlmaConfiguration
    {
        return $this->buildConfiguration($paymentMethod->getGatewayConfig()?->getConfig() ?? []);
    }

    public function reset(): void
    {
        $this->configurations = [];
    }

    public function getApiKeyByMode(?ChannelInterface $channel, string $mode): ?string
    {
        return $this->apiKeyOf($this->getConfiguration($channel), $mode);
    }

    /**
     * Once paid, an order keeps the keys of the Alma payment method that took
     * the payment, even if its channel has left Alma since.
     */
    public function getApiKeyOfOrder(OrderInterface $order, string $mode): ?string
    {
        $paymentMethod = $this->almaPaymentMethodOf($order);
        if ($paymentMethod === null) {
            return $this->getApiKeyByMode($order->getChannel(), $mode);
        }

        return $this->apiKeyOf($this->getConfigurationOf($paymentMethod), $mode);
    }

    private function apiKeyOf(AlmaConfiguration $configuration, string $mode): ?string
    {
        $key = match ($mode) {
            AlmaConfiguration::MODE_LIVE => $configuration->getApiKeyLive(),
            AlmaConfiguration::MODE_TEST => $configuration->getApiKeyTest(),
            default => null,
        };

        return $key === '' ? null : $key;
    }

    private function almaPaymentMethodOf(OrderInterface $order): ?PaymentMethodInterface
    {
        foreach (array_reverse($order->getPayments()->toArray()) as $payment) {
            $paymentMethod = $payment->getMethod();
            if ($paymentMethod instanceof PaymentMethodInterface && $this->isAlma($paymentMethod)) {
                return $paymentMethod;
            }
        }

        return null;
    }

    public function getAlmaPaymentMethod(?ChannelInterface $channel): ?PaymentMethodInterface
    {
        if ($channel === null) {
            return null;
        }

        foreach ($this->findAlmaPaymentMethods() as $paymentMethod) {
            if ($paymentMethod->hasChannel($channel)) {
                return $paymentMethod;
            }
        }

        return null;
    }

    /**
     * @return list<PaymentMethodInterface>
     */
    private function findAlmaPaymentMethods(): array
    {
        $almaPaymentMethods = [];
        foreach ($this->paymentMethodRepository->findAll() as $paymentMethod) {
            if ($paymentMethod instanceof PaymentMethodInterface && $this->isAlma($paymentMethod)) {
                $almaPaymentMethods[] = $paymentMethod;
            }
        }

        return $almaPaymentMethods;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildConfiguration(array $config): AlmaConfiguration
    {
        $configuration = new AlmaConfiguration();

        $configuration->setApiKeyLive($this->nullableString($config['api_key_live'] ?? null));
        $configuration->setApiKeyTest($this->nullableString($config['api_key_test'] ?? null));
        $configuration->setApiMode(
            \is_string($config['api_mode'] ?? null) && \in_array($config['api_mode'], AlmaConfiguration::MODES, true)
                ? $config['api_mode']
                : AlmaConfiguration::MODE_TEST,
        );
        $configuration->setMerchantId($this->nullableString($config['merchant_id'] ?? null));
        $configuration->setFeePlans($this->nullableArray($config['fee_plans'] ?? null));
        $configuration->setFeePlanOverrides($this->nullableArray($config['fee_plan_overrides'] ?? null));
        $configuration->setProductWidgetEnabled((bool) ($config['product_widget_enabled'] ?? true));
        // A config saved before the setting existed has no key: it keeps the off-site flow.
        $configuration->setInPageEnabled(($config['in_page_enabled'] ?? false) === true);
        $configuration->setUnifiedPaymentButtonEnabled(($config['unified_payment_button_enabled'] ?? false) === true);
        $configuration->setDisplayTexts($this->nullableArray($config['display_texts'] ?? null));
        $configuration->setExcludedTaxonCodes($this->stringList($config['excluded_taxon_codes'] ?? null));
        $configuration->setExclusionMessages($this->nullableArray($config['exclusion_messages'] ?? null));
        $configuration->setCartWidgetEnabled((bool) ($config['cart_widget_enabled'] ?? true));
        $configuration->setCartWidgetPosition(
            \in_array($config['cart_widget_position'] ?? null, CartWidgetPosition::ALL, true)
                ? $config['cart_widget_position']
                : CartWidgetPosition::DEFAULT,
        );
        $configuration->setCartWidgetMonochrome((bool) ($config['cart_widget_monochrome'] ?? true));
        $configuration->setCartWidgetHideBorder((bool) ($config['cart_widget_hide_border'] ?? false));
        $configuration->setCartWidgetTransitionDelay($this->transitionDelay($config['cart_widget_transition_delay'] ?? null));
        $configuration->setCartWidgetShownIfNotEligible((bool) ($config['cart_widget_show_if_not_eligible'] ?? true));

        return $configuration;
    }

    private function nullableString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function nullableArray(mixed $value): ?array
    {
        return \is_array($value) && $value !== [] ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => \is_string($item) && $item !== ''));
    }

    private function transitionDelay(mixed $value): int
    {
        return \is_int($value) && $value >= AlmaConfiguration::MIN_CART_WIDGET_TRANSITION_DELAY
            ? $value
            : AlmaConfiguration::DEFAULT_CART_WIDGET_TRANSITION_DELAY;
    }
}
