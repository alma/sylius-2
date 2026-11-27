<?php

declare(strict_types=1);

namespace Alma\Sylius\CartWidget;

/**
 * Cart page positions the merchant can pick for the cart widget. Each one is
 * an `alma_cart_widget` hookable of `config/twig_hooks/shop/cart/index.yaml`.
 */
final class CartWidgetPosition
{
    public const AFTER_SUMMARY = 'after_summary';
    public const BEFORE_SUMMARY = 'before_summary';
    public const AFTER_CHECKOUT_BUTTON = 'after_checkout_button';
    public const AFTER_ITEMS = 'after_items';

    public const DEFAULT = self::AFTER_SUMMARY;

    public const ALL = [
        self::AFTER_SUMMARY,
        self::BEFORE_SUMMARY,
        self::AFTER_CHECKOUT_BUTTON,
        self::AFTER_ITEMS,
    ];

    private function __construct()
    {
    }
}
