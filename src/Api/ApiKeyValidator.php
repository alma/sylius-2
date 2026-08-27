<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

use Alma\Client\Application\Exception\Endpoint\MerchantEndpointException;

class ApiKeyValidator
{
    public function __construct(
        private readonly MerchantEndpointFactoryInterface $endpointFactory,
    ) {
    }

    public function validate(string $apiKey, string $mode): ApiKeyValidationResult
    {
        try {
            $merchant = $this->endpointFactory->make($apiKey, $mode)->me();
        } catch (MerchantEndpointException) {
            return ApiKeyValidationResult::invalid();
        }

        if (!$merchant->canCreatePayments()) {
            return ApiKeyValidationResult::cannotCreatePayments();
        }

        return ApiKeyValidationResult::success($merchant->getId());
    }
}
