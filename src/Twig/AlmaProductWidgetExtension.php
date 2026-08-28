<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Alma\Sylius\ProductWidget\AlmaProductWidgetConfigProvider;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the Twig function `alma_product_widget_config(variant)` that returns
 * the JSON-shaped config consumed by the product-page widget bootstrap, or
 * `null` when the widget MUST not be rendered (toggle off, no merchant id, no
 * priced variant…). The Twig template checks for null and emits nothing in
 * that case — cf. spec Requirement « Widget is silent when disabled ».
 */
final class AlmaProductWidgetExtension extends AbstractExtension
{
    public function __construct(
        private readonly AlmaProductWidgetConfigProvider $provider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_product_widget_config', $this->build(...)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function build(?ProductVariantInterface $variant): ?array
    {
        return $this->provider->build($variant);
    }
}
