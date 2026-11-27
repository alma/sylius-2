<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\Endpoint\ConfigurationEndpoint;

interface ConfigurationEndpointFactoryInterface
{
    public function make(string $apiKey, string $mode): ConfigurationEndpoint;
}
