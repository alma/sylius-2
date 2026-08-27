<?php

declare(strict_types=1);

namespace Alma\Sylius\Cache;

use Alma\Client\Application\DTO\AddressDto;
use Alma\Sylius\Eligibility\AlmaEligibility;
use Alma\Sylius\Entity\AlmaConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Single-entry session cache: a new key overwrites the previous result, so the
 * session never accumulates stale entries. Invalidation is implicit — when any
 * of the inputs that compose the key change (cart total, addresses, mode,
 * order's updated_at), the lookup misses and the caller re-fetches.
 */
final class SessionEligibilityCache
{
    private const SESSION_KEY = '_alma_eligibility';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, AlmaEligibility>|null
     */
    public function get(string $cacheKey): ?array
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }
        $stored = $session->get(self::SESSION_KEY);
        if (!is_array($stored) || ($stored['key'] ?? null) !== $cacheKey) {
            return null;
        }
        $result = $stored['result'] ?? null;

        return is_array($result) ? $result : null;
    }

    /**
     * @param array<string, AlmaEligibility> $result
     */
    public function set(string $cacheKey, array $result): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }
        $session->set(self::SESSION_KEY, ['key' => $cacheKey, 'result' => $result]);
    }

    public function computeKey(
        OrderInterface $order,
        AlmaConfiguration $configuration,
        ?AddressDto $billing,
        ?AddressDto $shipping,
    ): string {
        $payload = [
            'order' => $order->getId(),
            'total' => $order->getTotal(),
            'updated_at' => $order->getUpdatedAt()?->getTimestamp(),
            'mode' => $configuration->getApiMode(),
            'billing' => self::addressSignature($billing),
            'shipping' => self::addressSignature($shipping),
        ];

        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function addressSignature(?AddressDto $address): ?array
    {
        return $address?->toArray();
    }

    private function session(): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
