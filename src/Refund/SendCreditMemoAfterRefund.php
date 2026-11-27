<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

final class SendCreditMemoAfterRefund
{
    public function __construct(public readonly string $creditMemoNumber)
    {
    }
}
