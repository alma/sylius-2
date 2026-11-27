<?php

declare(strict_types=1);

namespace Alma\Sylius\OrderInformation;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrderStatusLabelResolver
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function resolve(string $state, OrderInterface $order): string
    {
        $key = 'sylius.ui.' . $state;
        $locale = $order->getChannel()?->getDefaultLocale()?->getCode();
        $label = $this->translator->trans($key, [], null, $locale);

        return $label === $key || $label === '' ? $state : $label;
    }
}
