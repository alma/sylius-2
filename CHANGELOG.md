# Changelog

## v1.1.0 - 2026-11-27

### Changes

### 🚀 New Features

- feat(module-configuration,checkout,payment-creation): unified payment button
- feat(module-configuration): disabling Alma removes the payment method, not the plugin
- feat(checkout): show the official Alma logo and plan badges
- feat(cms-data-export): Alma reads the shop's environment and Alma configuration
- feat(business-events): report carts and paid orders to Alma
- feat(module-configuration): configure Alma per channel
- feat(refund): partial and multiple refunds through sylius/refund-plugin
- feat(module-configuration): Alma visible in Test mode for admins only
- feat(order-information): send order status and shipment tracking to Alma
- feat(in-page): pay with Alma in a modal on Sylius (v6)
- feat(category-exclusion): exclusion message on the cart widget
- feat(cart-widget): Alma widget on the cart page
- feat(category-exclusion): exclude product categories from Alma

#### Contributors

@Benjamin-Freoua-Alma, @webaaz

## v1.0.0 - 2026-09-01

### Changes

### 🚀 New Features

- feat(api): plugin User-Agent components on every Alma API call
- feat(module-configuration): titled Display sub-parts, widget toggle moved in
- feat(module-configuration): display group identity labels
- feat(product-widget): enable the widget by default

#### Contributors

@Francois-Gomis

## v0.0.1 - 2026-08-27

Alma payment plugin for Sylius 2 — let your customers pay in installments or later, directly from your Sylius store.

### Changes

- Offer Alma's installment and pay-later plans at checkout
- Get started in minutes: connect your Alma account from the back office and pick the payment plans you want to offer
- Secure payment on Alma's hosted payment page, with reliable order confirmation
- Refund orders without leaving Sylius
- Show shoppers the available payment plans right on the product page
