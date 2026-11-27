<?php

declare(strict_types=1);

namespace Alma\Sylius\Refund;

use Alma\Sylius\Payment\AlmaPaidPaymentFinder;
use Alma\Sylius\Payment\AlmaRefundHelper;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\RefundPlugin\Event\UnitsRefunded;
use Sylius\RefundPlugin\ProcessManager\UnitsRefundedProcessStepInterface;

/**
 * Runs inside the Refund Plugin's refund command, whose Doctrine transaction
 * rolls the whole refund back when Alma fails. That holds only while the
 * Refund Plugin events are handled synchronously, their default.
 */
final class AlmaRefundStep implements UnitsRefundedProcessStepInterface
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AlmaPaymentMethodResolver $methodResolver,
        private readonly AlmaPaidPaymentFinder $almaPaidPaymentFinder,
        private readonly AlmaRefundHelper $refundHelper,
        private readonly AlmaRefundCommitWatcher $commitWatcher,
    ) {
    }

    public function next(UnitsRefunded $unitsRefunded): void
    {
        $refundMethod = $this->paymentMethodRepository->find($unitsRefunded->paymentMethodId());
        if (!$refundMethod instanceof PaymentMethodInterface || !$this->methodResolver->isAlma($refundMethod)) {
            return;
        }

        $order = $this->orderRepository->findOneByNumber($unitsRefunded->orderNumber());
        $almaPayment = $order === null ? null : $this->almaPaidPaymentFinder->find($order);
        if ($almaPayment === null) {
            throw new \LogicException(sprintf('Cannot refund with Alma: order "%s" was not paid with Alma.', $unitsRefunded->orderNumber()));
        }

        $this->refundHelper->refund($almaPayment, $unitsRefunded->amount());
        $this->commitWatcher->expect($almaPayment, $unitsRefunded->orderNumber(), $unitsRefunded->amount());
    }
}
