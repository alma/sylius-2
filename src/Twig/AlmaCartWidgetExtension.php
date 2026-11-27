<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\CartWidget\AlmaCartWidgetConfigProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes `alma_cart_widget(position)`: the widget payload or the exclusion
 * message for that cart page position, or `null` when nothing may be
 * rendered there.
 */
final class AlmaCartWidgetExtension extends AbstractExtension
{
    public function __construct(
        private readonly AlmaCartWidgetConfigProvider $provider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_cart_widget', $this->render(...)),
        ];
    }

    /**
     * @return array{config: array<string, mixed>|null, exclusionMessage: string|null}|null
     */
    public function render(string $position): ?array
    {
        return $this->provider->render($position);
    }
}
