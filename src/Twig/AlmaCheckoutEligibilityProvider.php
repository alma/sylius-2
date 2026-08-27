<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\Cache\SessionEligibilityCache;
use Alma\Sylius\Eligibility\AlmaEligibility;
use Alma\Sylius\Eligibility\EligibilityFetcher;
use Alma\Sylius\Eligibility\EligibilityFetchFailedException;
use Alma\Sylius\Eligibility\SyliusAddressMapper;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Session-scoped cache (cf. spec checkout "Eligibility is cached at the
 * checkout session level"): without it, every payment-method render would
 * hit Alma. The key is composed so any spec invalidator (billing, shipping,
 * cart mutation, mode switch) produces a miss.
 * Fetch failures are swallowed so the checkout still renders; downstream
 * "Alma hidden on eligibility failure" handles the empty result.
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

        $configuration = $this->configurationResolver->getConfiguration();
        $email = $order->getCustomer()?->getEmail();
        $billing = $this->addressMapper->toAlmaAddress($order->getBillingAddress(), $email);
        $shipping = $this->addressMapper->toAlmaAddress($order->getShippingAddress(), $email);

        $cacheKey = $this->cache->computeKey($order, $configuration, $billing, $shipping);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $this->memoized[$memoKey] = $cached;
        }

        try {
            $result = $this->fetcher->fetch($configuration, $order->getTotal(), $billing, $shipping);
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
