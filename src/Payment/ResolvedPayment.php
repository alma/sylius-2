<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Domain\Entity\Payment as AlmaPayment;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

/**
 * Bundles the resolver output (Alma server-side payment + matched Sylius
 * order + matched Sylius payment + persisted alma_mode) produced by
 * {@see AlmaPaymentResolver::resolve()}. Consumed by the return route (E1+)
 * and the IPN route (F1+) to make state decisions against the authoritative
 * Alma payment, never against the incoming query.
 */
final class ResolvedPayment
{
    public function __construct(
        public readonly AlmaPayment $almaPayment,
        public readonly OrderInterface $order,
        public readonly SyliusPaymentInterface $syliusPayment,
        public readonly string $almaMode,
    ) {
    }
}
