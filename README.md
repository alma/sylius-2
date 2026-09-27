# Alma payment plugin for Sylius 2

This plugin adds a new payment method to Sylius 2, which allows you to offer installments payments to your customers using [Alma](https://almapay.com).

## Requirements

- PHP `^8.2`
- Sylius `^2.0`
- An Alma merchant account — sign up at [almapay.com](https://almapay.com), then retrieve your API keys from the [Alma dashboard](https://dashboard.getalma.eu)

## Installation

```bash
composer require alma/sylius-2-payment
```

Register the plugin in `config/bundles.php`:

```php
return [
    // ...
    Alma\Sylius\AlmaSyliusPlugin::class => ['all' => true],
];
```

Import the plugin routes by creating `config/routes/alma_sylius.yaml`:

```yaml
alma_sylius:
    resource: '@AlmaSyliusPlugin/config/routes/payment.yaml'
```

Then clear the cache:

```bash
php bin/console cache:clear
```

## Configuration

1. In the Sylius admin, go to **Payment methods** and create a new method using the **Alma** gateway.
2. Enter your Alma API keys (Live and Test) and select the mode to use.
3. Enable the fee plans you want to offer (e.g. pay in 3 or 4 installments).
4. Enable the payment method on the channels where you want Alma available.

## Features

- **Eligibility at checkout** — Alma is offered only when the cart is eligible, with the available installment plans for the customer to choose from.
- **Off-site payment** — the customer is redirected to Alma's payment page and back to your shop after completing (or cancelling) the payment.
- **Refunds** — refund an order through the native Sylius admin; the refund is propagated to Alma automatically.

## Support

- Documentation: [docs.almapay.com](https://docs.almapay.com)
- Contact: [support@getalma.eu](mailto:support@getalma.eu)

## Development

```bash
composer install
vendor/bin/phpunit          # unit suite (cipher, IPN verifier, fee plan grouper)
vendor/bin/phpstan analyse  # level max, baseline-tracked legacy findings
```

CI runs both on PHP 8.2, 8.3 and 8.4. The PHPStan baseline
(`phpstan-baseline.neon`) exists so the whole codebase is analysed at level
max from day one: existing findings are tracked, new ones fail the build,
and the baseline is expected to shrink, never grow.

## License

This plugin is released under the [MIT License](LICENSE).
