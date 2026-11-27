<?php

declare(strict_types=1);

namespace Alma\Sylius\CmsDataExport;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class AlmaChannels
{
    public static function primaryOf(PaymentMethodInterface $paymentMethod): ?ChannelInterface
    {
        $channels = array_values(array_filter(
            $paymentMethod->getChannels()->toArray(),
            static fn (mixed $channel): bool => $channel instanceof ChannelInterface,
        ));
        usort($channels, static fn (ChannelInterface $a, ChannelInterface $b): int => strcmp((string) $a->getCode(), (string) $b->getCode()));

        return $channels[0] ?? null;
    }
}
