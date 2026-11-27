<?php

declare(strict_types=1);

namespace Alma\Sylius\EventListener;

use Alma\Sylius\Payment\AlmaRefundHelper;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Resource\Exception\UpdateHandlingException;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Runs before Sylius applies the `sylius_payment` refund transition, so that a
 * failed Alma refund aborts it. Sylius' ResourceController turns only an
 * UpdateHandlingException into its error flash: any other exception reaches
 * the merchant as an HTTP 500 page.
 */
final class AlmaPaymentRefundListener
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $methodResolver,
        private readonly AlmaRefundHelper $refundHelper,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof SyliusPaymentInterface) {
            return;
        }

        $method = $payment->getMethod();
        if ($method === null || !$this->methodResolver->isAlma($method)) {
            return;
        }

        try {
            $this->refundHelper->refund($payment);
        } catch (\Throwable $almaFailure) {
            // Sylius' UpdateHandlingException takes only an \Exception as previous.
            throw new UpdateHandlingException(previous: $almaFailure instanceof \Exception ? $almaFailure : new \RuntimeException('Alma refund failed', previous: $almaFailure));
        }
    }
}
