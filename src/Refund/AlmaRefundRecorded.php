<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

final class AlmaRefundRecorded
{
    public function __construct(public readonly int $refundKey)
    {
    }
}
