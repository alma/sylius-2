<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

use Alma\Sylius\Payment\AlmaPaidPaymentFinder;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;

final class AlmaRefundPaymentMethodsProvider implements RefundPaymentMethodsProviderInterface
{
    public function __construct(
        private readonly RefundPaymentMethodsProviderInterface $inner,
        private readonly AlmaPaidPaymentFinder $almaPaidPaymentFinder,
    ) {
    }

    /** @return PaymentMethodInterface[] */
    public function findForOrder(OrderInterface $order): array
    {
        $refundMethods = $this->inner->findForOrder($order);
        $almaPayment = $this->almaPaidPaymentFinder->find($order);
        if ($almaPayment?->getState() === PaymentInterface::STATE_COMPLETED) {
            $almaMethod = $almaPayment->getMethod();
            if ($almaMethod instanceof PaymentMethodInterface && !\in_array($almaMethod, $refundMethods, true)) {
                $refundMethods[] = $almaMethod;
            }

            return $refundMethods;
        }

        return array_values(array_filter(
            $refundMethods,
            static fn (PaymentMethodInterface $method): bool => $method->getGatewayConfig()?->getFactoryName() !== AlmaPaymentMethodResolver::GATEWAY_FACTORY_NAME,
        ));
    }
}
