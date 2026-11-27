<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\Resolver\AlmaStorefrontVisibility;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AlmaStorefrontExtension extends AbstractExtension
{
    public function __construct(
        private readonly AlmaStorefrontVisibility $storefrontVisibility,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_storefront_visible', fn (): bool => !$this->storefrontVisibility->isHidden()),
        ];
    }
}
