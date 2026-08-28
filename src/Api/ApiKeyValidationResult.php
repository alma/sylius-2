<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

final class ApiKeyValidationResult
{
    private function __construct(
        private readonly ?string $merchantId,
        private readonly ?ApiKeyValidationError $error,
    ) {
    }

    public static function success(string $merchantId): self
    {
        return new self($merchantId, null);
    }

    public static function invalid(): self
    {
        return new self(null, ApiKeyValidationError::Invalid);
    }

    public static function cannotCreatePayments(): self
    {
        return new self(null, ApiKeyValidationError::CannotCreatePayments);
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    public function getError(): ?ApiKeyValidationError
    {
        return $this->error;
    }
}
