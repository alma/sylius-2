<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Doctrine\Persistence\ObjectRepository;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Sylius\RefundPlugin\StateResolver\RefundPaymentCompletedStateApplierInterface;

/**
 * AlmaRefundStep refunds an Alma refund payment with Alma later in the same
 * refund command: a refusal rolls this completion back with the rest.
 */
final class AlmaRefundPaymentGeneratedHandler
{
    /** @param ObjectRepository<RefundPaymentInterface> $refundPaymentRepository */
    public function __construct(
        private readonly ObjectRepository $refundPaymentRepository,
        private readonly AlmaPaymentMethodResolver $methodResolver,
        private readonly RefundPaymentCompletedStateApplierInterface $completedStateApplier,
    ) {
    }

    public function __invoke(RefundPaymentGenerated $event): void
    {
        $refundPayment = $this->refundPaymentRepository->find($event->id());
        if ($refundPayment === null || !$this->methodResolver->isAlma($refundPayment->getPaymentMethod())) {
            return;
        }

        $this->completedStateApplier->apply($refundPayment);
    }
}
