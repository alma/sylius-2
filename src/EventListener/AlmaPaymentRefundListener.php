<?php

declare(strict_types=1);

namespace Alma\Sylius\EventListener;

use Alma\Sylius\Payment\AlmaRefundHelper;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Hooks on the Symfony Workflow PRE-transition event for the Sylius native
 * `sylius_payment.refund` transition (`completed → refunded`, terminal).
 *
 * The listener filters out non-Alma payments (other gateways pass through
 * untouched), and for Alma payments delegates to {@see AlmaRefundHelper} which
 * calls Alma server-to-server. Any exception propagates → Sylius's
 * ResourceController catches it, renders a generic flash error and the state
 * machine transition is aborted (pessimistic policy: Alma BEFORE Sylius).
 *
 * Wired via `config/services.yaml` with the tag
 * `kernel.event_listener`, event `workflow.sylius_payment.transition.refund`.
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

        $this->refundHelper->refund($payment);
    }
}
