<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\Endpoint\MerchantEndpoint;

interface MerchantEndpointFactoryInterface
{
    public function make(string $apiKey, string $mode): MerchantEndpoint;
}
