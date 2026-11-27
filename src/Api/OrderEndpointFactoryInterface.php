<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\Endpoint\OrderEndpoint;

interface OrderEndpointFactoryInterface
{
    public function make(string $apiKey, string $mode): OrderEndpoint;
}
