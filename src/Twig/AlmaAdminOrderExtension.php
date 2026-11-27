<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\InPage\OrderAlmaPaymentsProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AlmaAdminOrderExtension extends AbstractExtension
{
    public function __construct(
        private readonly OrderAlmaPaymentsProvider $paymentsProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_order_payments', $this->paymentsProvider->forOrder(...)),
        ];
    }
}
