<?php

declare(strict_types=1);

namespace Alma\Sylius\Factory;

use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * @implements PaymentMethodFactoryInterface<PaymentMethodInterface>
 */
final class AlmaPaymentMethodFactory implements PaymentMethodFactoryInterface
{
    /**
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $decoratedFactory
     */
    public function __construct(
        private readonly PaymentMethodFactoryInterface $decoratedFactory,
    ) {
    }

    public function createNew(): PaymentMethodInterface
    {
        return $this->decoratedFactory->createNew();
    }

    public function createWithGateway(string $gatewayFactory): PaymentMethodInterface
    {
        $paymentMethod = $this->decoratedFactory->createWithGateway($gatewayFactory);
        if ($gatewayFactory === AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME) {
            $paymentMethod->setEnabled(false);
        }

        return $paymentMethod;
    }
}
