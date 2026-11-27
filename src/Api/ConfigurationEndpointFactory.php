<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\CurlClient;
use Alma\Client\Application\Endpoint\ConfigurationEndpoint;

final class ConfigurationEndpointFactory implements ConfigurationEndpointFactoryInterface
{
    public function __construct(
        private readonly ClientConfigurationFactory $configurationFactory,
    ) {
    }

    public function make(string $apiKey, string $mode): ConfigurationEndpoint
    {
        $client = new CurlClient($this->configurationFactory->make($apiKey, $mode));

        return new ConfigurationEndpoint($client);
    }
}
