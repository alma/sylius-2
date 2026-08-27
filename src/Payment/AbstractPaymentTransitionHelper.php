<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * Common base for the idempotent state-machine transition helpers applied on
 * the Sylius Payment (`AlmaPaymentCompletionHelper`, `AlmaPaymentCancellationHelper`).
 *
 * Each subclass declares which transition to apply (`getTransition()`) and the
 * pair of log keys it emits on no-op / applied. The shared `doApply()` :
 *
 *  1. Guards via `StateMachineInterface::can()` to short-circuit replays —
 *     idempotence by design, no double cascade through Sylius native listeners.
 *  2. Applies the transition through the abstraction.
 *  3. Flushes Doctrine explicitly — the callback routes (return / cancel / IPN)
 *     are not Sylius CRUD actions and have no auto-flush middleware.
 *  4. Emits a structured info log with the standard correlation keys
 *     (`payment_id`, `alma_payment_id`, `order` or `current_state`).
 */
abstract class AbstractPaymentTransitionHelper
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    abstract protected function getTransition(): string;

    abstract protected function getSkippedLogKey(): string;

    abstract protected function getAppliedLogKey(): string;

    /**
     * @return bool true if the transition was applied on this call,
     *              false if it was a no-op (state already terminal)
     */
    protected function doApply(ResolvedPayment $resolved): bool
    {
        $payment = $resolved->syliusPayment;

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $this->getTransition())) {
            $this->logger->info($this->getSkippedLogKey(), [
                'payment_id' => $payment->getId(),
                'current_state' => $payment->getState(),
                'alma_payment_id' => $resolved->almaPayment->getId(),
            ]);

            return false;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $this->getTransition());
        $this->entityManager->flush();

        $this->logger->info($this->getAppliedLogKey(), [
            'payment_id' => $payment->getId(),
            'order' => $resolved->order->getId(),
            'alma_payment_id' => $resolved->almaPayment->getId(),
        ]);

        return true;
    }
}
