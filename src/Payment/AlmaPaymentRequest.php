<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Application\DTO\CustomerDto;
use Alma\Client\Application\DTO\OrderDto;
use Alma\Client\Application\DTO\PaymentDto;

/**
 * Bundles the three DTOs needed to call PaymentEndpoint::create().
 */
final class AlmaPaymentRequest
{
    public function __construct(
        public readonly PaymentDto $payment,
        public readonly OrderDto $order,
        public readonly CustomerDto $customer,
    ) {
    }
}
