<?php

declare(strict_types=1);

namespace Alma\Sylius\DependencyInjection\Compiler;

use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The Refund Plugin offers as refund methods only the payment methods whose
 * gateway is listed in this parameter (`offline` by default).
 */
final class RefundPluginSupportedGatewaysPass implements CompilerPassInterface
{
    private const SUPPORTED_GATEWAYS = 'sylius_refund.supported_gateways';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::SUPPORTED_GATEWAYS)) {
            return;
        }

        /** @var list<string> $supportedGateways */
        $supportedGateways = $container->getParameter(self::SUPPORTED_GATEWAYS);
        if (\in_array(AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME, $supportedGateways, true)) {
            return;
        }

        $supportedGateways[] = AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME;
        $container->setParameter(self::SUPPORTED_GATEWAYS, $supportedGateways);
    }
}
