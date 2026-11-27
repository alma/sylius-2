<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\BusinessEvents\CheckoutEligibilityRecorder;
use Alma\Sylius\Cache\SessionEligibilityCache;
use Alma\Sylius\Eligibility\AlmaEligibility;
use Alma\Sylius\Eligibility\EligibilityFetcher;
use Alma\Sylius\Eligibility\EligibilityFetchFailedException;
use Alma\Sylius\Eligibility\SyliusAddressMapper;
use Alma\Sylius\Exclusion\ExcludedTaxonMatcher;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Resolver\AlmaStorefrontVisibility;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Session-scoped cache (cf. spec checkout "Eligibility is cached at the
 * checkout session level"): without it, every payment-method render would
 * hit Alma. The key is composed so any spec invalidator (billing, shipping,
 * cart mutation, mode switch) produces a miss.
 * Fetch failures are swallowed so the checkout still renders; downstream
 * "Alma hidden on eligibility failure" handles the empty result.
 * An order containing an excluded product short-circuits to an empty result
 * before the cache and Alma (cf. specs-sylius/category-exclusion), so every
 * consumer hides Alma without any eligibility call. So does Alma hidden from
 * the visitor in Test mode (cf. specs-sylius/module-configuration).
 */
class AlmaCheckoutEligibilityProvider implements ResetInterface
{
    /**
     * One select_payment render resolves eligibilities three times (methods
     * resolver, form PRE_SET_DATA, template) and the spec invalidators cannot
     * change within a single request, so the result is memoized per order
     * instance. ResetInterface clears it between requests on long-running
     * runtimes (kernel.reset via autoconfigure).
     *
     * @var array<int, array<string, AlmaEligibility>>
     */
    private array $memoized = [];

    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly SyliusAddressMapper $addressMapper,
        private readonly EligibilityFetcher $fetcher,
        private readonly SessionEligibilityCache $cache,
        private readonly ExcludedTaxonMatcher $excludedTaxonMatcher,
        private readonly AlmaStorefrontVisibility $storefrontVisibility,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?CheckoutEligibilityRecorder $eligibilityRecorder = null,
    ) {
    }

    /**
     * @return array<string, AlmaEligibility> keyed by plan key
     */
    public function getEligibilities(OrderInterface $order): array
    {
        $memoKey = spl_object_id($order);
        if (isset($this->memoized[$memoKey])) {
            return $this->memoized[$memoKey];
        }

        if ($this->storefrontVisibility->isHidden()) {
            return $this->memoized[$memoKey] = [];
        }

        $configuration = $this->configurationResolver->getConfiguration($order->getChannel());
        if ($this->excludedTaxonMatcher->isOrderExcluded($order, $configuration->getExcludedTaxonCodes())) {
            $this->logger->info('alma.category_exclusion.checkout_hidden', ['order' => $order->getId()]);
            $this->eligibilityRecorder?->record($order, []);

            return $this->memoized[$memoKey] = [];
        }

        $email = $order->getCustomer()?->getEmail();
        $billing = $this->addressMapper->toAlmaAddress($order->getBillingAddress(), $email);
        $shipping = $this->addressMapper->toAlmaAddress($order->getShippingAddress(), $email);

        $cacheKey = $this->cache->computeKey($order, $configuration, $billing, $shipping);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->eligibilityRecorder?->record($order, $cached);

            return $this->memoized[$memoKey] = $cached;
        }

        try {
            $result = $this->fetcher->fetch($configuration, $order->getTotal(), $billing, $shipping);
            $this->eligibilityRecorder?->record($order, $result);
        } catch (EligibilityFetchFailedException) {
            $result = [];
        }

        $this->cache->set($cacheKey, $result);

        return $this->memoized[$memoKey] = $result;
    }

    public function reset(): void
    {
        $this->memoized = [];
    }
}
