<?php

declare(strict_types=1);

namespace Alma\Sylius\Resolver;

use Alma\Sylius\Twig\AlmaCheckoutEligibilityProvider;
use Sylius\Component\Core\Model\PaymentInterface as CorePaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

/**
 * Removes the Alma payment method from the available choices when the cart
 * yields zero eligible plan, so a customer cannot pick (or POST) Alma when
 * Alma would have nothing to offer downstream.
 */
final class AlmaEligibilityPaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    public function __construct(
        private readonly PaymentMethodsResolverInterface $decorated,
        private readonly AlmaCheckoutEligibilityProvider $eligibilityProvider,
        private readonly AlmaPaymentMethodResolver $almaResolver,
    ) {
    }

    public function getSupportedMethods(PaymentInterface $subject): array
    {
        $methods = $this->decorated->getSupportedMethods($subject);

        if (!$subject instanceof CorePaymentInterface) {
            return $methods;
        }
        $order = $subject->getOrder();
        if ($order === null) {
            return $methods;
        }

        if ($this->hasAnyEligiblePlan($order)) {
            return $methods;
        }

        return array_values(array_filter(
            $methods,
            fn ($method): bool => !$this->almaResolver->isAlma($method),
        ));
    }

    public function supports(PaymentInterface $subject): bool
    {
        return $this->decorated->supports($subject);
    }

    private function hasAnyEligiblePlan(\Sylius\Component\Core\Model\OrderInterface $order): bool
    {
        foreach ($this->eligibilityProvider->getEligibilities($order) as $eligibility) {
            if ($eligibility->isEligible()) {
                return true;
            }
        }

        return false;
    }
}
