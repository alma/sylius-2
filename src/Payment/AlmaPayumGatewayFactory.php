<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

/**
 * Minimal Payum gateway factory for the `alma` factory name.
 *
 * The HTTP call to Alma's PaymentEndpoint::create() is performed by
 * {@see Alma\Sylius\Payment\Action\AlmaCaptureAction}, which uses the
 * plugin's own services (PaymentEndpointFactory, AlmaPaymentMethodResolver)
 * to resolve credentials and build DTOs. This factory therefore only sets
 * the Payum factory_name / factory_title metadata and does not wire any
 * client of its own — actions are attached via the `payum.action factory=alma`
 * tag on the application side.
 */
final class AlmaPayumGatewayFactory extends GatewayFactory
{
    public const FACTORY_NAME = 'alma';

    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => self::FACTORY_NAME,
            'payum.factory_title' => 'Alma',
        ]);
    }
}
