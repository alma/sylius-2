<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\CurlClient;
use Alma\Client\Application\Endpoint\PaymentEndpoint;

final class PaymentEndpointFactory implements PaymentEndpointFactoryInterface
{
    public function __construct(
        private readonly ClientConfigurationFactory $configurationFactory,
    ) {
    }

    public function make(string $apiKey, string $mode): PaymentEndpoint
    {
        $client = new CurlClient($this->configurationFactory->make($apiKey, $mode));

        return new PaymentEndpoint($client);
    }
}
