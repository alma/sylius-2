<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\Endpoint\PaymentEndpoint;

interface PaymentEndpointFactoryInterface
{
    public function make(string $apiKey, string $mode): PaymentEndpoint;
}
