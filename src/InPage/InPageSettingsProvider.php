<?php

declare(strict_types=1);

namespace Alma\Sylius\InPage;

use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Payment\AlreadyPaidOrder;
use Alma\Sylius\Payment\FeePlanKey;
use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Resolver\AlmaStorefrontVisibility;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Settings handed to Alma's In-Page library by the payment-step and order-page
 * templates. Only public data: the merchant id, the environment, the language,
 * the amount and the plan — never an API key.
 */
class InPageSettingsProvider
{
    /**
     * Alma's official CDN, at the major version of the In-Page library only:
     * Alma ships its minor and patch versions without a module release.
     */
    public const SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/@alma/in-page@2/dist/index.umd.js';

    /** Languages the In-Page frame and modal are available in. */
    private const SUPPORTED_LOCALES = ['DE', 'EN', 'ES', 'FR', 'IT', 'NL', 'PT'];

    private const FALLBACK_LOCALE = 'EN';

    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly LocaleContextInterface $localeContext,
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
        private readonly AlmaStorefrontVisibility $storefrontVisibility,
    ) {
    }

    /**
     * Null when the In-Page flow is off or Alma is hidden from this visitor.
     *
     * @return array{merchantId: string, environment: string, locale: string, amountInCents: int}|null
     */
    public function frameSettings(OrderInterface $order): ?array
    {
        $configuration = $this->configurationResolver->getConfiguration($order->getChannel());
        $merchantId = $configuration->getMerchantId();
        if (!$configuration->isInPageEnabled() || $merchantId === null || $this->storefrontVisibility->isHidden()) {
            return null;
        }

        return [
            'merchantId' => $merchantId,
            'environment' => $configuration->getApiMode() === AlmaConfiguration::MODE_LIVE ? 'LIVE' : 'TEST',
            'locale' => $this->locale(),
            'amountInCents' => $order->getTotal(),
        ];
    }

    /**
     * @return array{installmentsCount: int, deferredDays: int, deferredMonths: int}
     */
    public static function planSettings(string $planKey): array
    {
        $feePlan = FeePlanKey::parse($planKey);

        return [
            'installmentsCount' => $feePlan->installmentsCount,
            'deferredDays' => $feePlan->deferredDays,
            'deferredMonths' => $feePlan->deferredMonths,
        ];
    }

    /**
     * The modal opens for an In-Page Alma payment of this order only while the
     * order still awaits payment on its Sylius Payment in state `new`.
     *
     * @return array<string, int|string>|null
     */
    public function modalSettings(OrderInterface $order, ?string $almaPaymentId): ?array
    {
        if ($almaPaymentId === null || $almaPaymentId === '') {
            return null;
        }

        $frameSettings = $this->frameSettings($order);
        if ($frameSettings === null) {
            return null;
        }

        $reference = $this->referenceRepository->findOneByAlmaPaymentId($almaPaymentId);
        if (
            $reference === null
            || $reference->getSyliusOrder() !== $order
            || !$reference->isInPage()
            || $reference->getFeePlanKey() === null
            || $reference->getSyliusPayment()->getState() !== PaymentInterface::STATE_NEW
            || AlreadyPaidOrder::is($order)
            || $order->getPaymentState() !== OrderPaymentStates::STATE_AWAITING_PAYMENT
        ) {
            return null;
        }

        return $frameSettings + self::planSettings($reference->getFeePlanKey()) + ['paymentId' => $almaPaymentId];
    }

    private function locale(): string
    {
        $language = strtoupper(substr($this->localeContext->getLocaleCode(), 0, 2));

        return \in_array($language, self::SUPPORTED_LOCALES, true) ? $language : self::FALLBACK_LOCALE;
    }
}
