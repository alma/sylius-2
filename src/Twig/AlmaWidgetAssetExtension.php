<?php

declare(strict_types=1);

namespace Alma\Sylius\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes `alma_widget_asset('js'|'css')`: the single source of the Alma
 * widget CDN URLs, shared by the product and cart widgets.
 */
final class AlmaWidgetAssetExtension extends AbstractExtension
{
    public const SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/@alma/widgets@4.x/dist/widgets.umd.js';
    public const STYLESHEET_URL = 'https://cdn.jsdelivr.net/npm/@alma/widgets@4.x/dist/widgets.min.css';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('alma_widget_asset', $this->url(...)),
        ];
    }

    public function url(string $type): string
    {
        return match ($type) {
            'js' => self::SCRIPT_URL,
            'css' => self::STYLESHEET_URL,
            default => throw new \InvalidArgumentException(sprintf('Unknown Alma widget asset type "%s".', $type)),
        };
    }
}
