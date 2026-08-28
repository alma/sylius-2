<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\Endpoint\EligibilityEndpoint;

interface EligibilityEndpointFactoryInterface
{
    public function make(string $apiKey, string $mode): EligibilityEndpoint;
}
