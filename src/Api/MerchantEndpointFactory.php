<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\CurlClient;
use Alma\Client\Application\Endpoint\MerchantEndpoint;

final class MerchantEndpointFactory implements MerchantEndpointFactoryInterface
{
    public function __construct(
        private readonly ClientConfigurationFactory $configurationFactory,
    ) {
    }

    public function make(string $apiKey, string $mode): MerchantEndpoint
    {
        $client = new CurlClient($this->configurationFactory->make($apiKey, $mode));

        return new MerchantEndpoint($client);
    }
}
