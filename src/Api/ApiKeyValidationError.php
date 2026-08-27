<?php

declare(strict_types=1);

namespace Alma\Sylius\Api;

enum ApiKeyValidationError: string
{
    case Invalid = 'invalid';
    case CannotCreatePayments = 'cannot_create_payments';
}
