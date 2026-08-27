<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\ClientConfiguration;
use Alma\Client\Application\CurlClient;
use Alma\Client\Application\Endpoint\EligibilityEndpoint;
use Alma\Client\Domain\ValueObject\Environment;

final class EligibilityEndpointFactory implements EligibilityEndpointFactoryInterface
{
    public function make(string $apiKey, string $mode): EligibilityEndpoint
    {
        $environment = new Environment($mode === 'live' ? Environment::LIVE_MODE : Environment::TEST_MODE);
        $configuration = new ClientConfiguration($apiKey, $environment);
        $client = new CurlClient($configuration);

        return new EligibilityEndpoint($client);
    }
}
