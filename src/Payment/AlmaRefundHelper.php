<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Application\DTO\RefundDto;
use Alma\Sylius\Api\PaymentEndpointFactoryInterface;
use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

/**
 * Callers invoke it before Sylius commits its refund, so that an Alma failure
 * (the exception propagates) rolls the Sylius refund back.
 */
class AlmaRefundHelper
{
    public const FULL_COMMENT = 'Full refund from Alma plugin';
    public const PARTIAL_COMMENT = 'Partial refund from Alma plugin';

    public function __construct(
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
        private readonly PaymentEndpointFactoryInterface $endpointFactory,
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param int|null $amount in cents; null refunds whatever is left
     */
    public function refund(SyliusPaymentInterface $payment, ?int $amount = null): void
    {
        $details = $payment->getDetails();
        $almaPaymentId = $details[AlmaPaymentDetailsKeys::PAYMENT_ID] ?? null;
        if (!is_string($almaPaymentId) || $almaPaymentId === '') {
            throw new \LogicException('Cannot refund Alma payment: missing alma_payment_id in payment details.');
        }

        $reference = $this->referenceRepository->findOneByAlmaPaymentId($almaPaymentId);
        if ($reference === null) {
            throw new \LogicException(sprintf('Cannot refund Alma payment: no AlmaPaymentReference indexed for alma_payment_id "%s".', $almaPaymentId));
        }

        $persistedMode = $reference->getAlmaMode();
        $apiKey = $this->configurationResolver->getApiKeyOfOrder($reference->getSyliusOrder(), $persistedMode);
        if ($apiKey === null) {
            throw new \LogicException(sprintf('Cannot refund Alma payment: persisted mode "%s" has no API key configured.', $persistedMode));
        }

        $order = $reference->getSyliusOrder();
        $alreadyRefunded = (int) ($details[AlmaPaymentDetailsKeys::AMOUNT_REFUNDED] ?? 0);
        $remainder = $order->getTotal() - $alreadyRefunded;
        $isFull = $amount === null || $amount === $remainder;
        $refundedAmount = $isFull ? $remainder : $amount;
        $comment = $isFull ? self::FULL_COMMENT : self::PARTIAL_COMMENT;

        $refundDto = (new RefundDto())
            ->setMerchantReference((string) $order->getNumber())
            ->setComment($comment);
        // Alma refunds whatever is left on the payment when no amount is set.
        if (!$isFull) {
            $refundDto->setAmount($refundedAmount);
        }

        try {
            $almaPayment = $this->endpointFactory->make($apiKey, $persistedMode)->refund($almaPaymentId, $refundDto);
        } catch (\Throwable $almaFailure) {
            $this->logger->error('alma.refund.failed', [
                'payment_id' => $payment->getId(),
                'order_number' => $order->getNumber(),
                'alma_payment_id' => $almaPaymentId,
                'mode' => $persistedMode,
                'amount' => $refundedAmount,
                'error' => $almaFailure::class,
            ]);

            throw $almaFailure;
        }

        $details[AlmaPaymentDetailsKeys::AMOUNT_REFUNDED] = $almaPayment->getAmountRefunded();
        $details[AlmaPaymentDetailsKeys::REFUNDS][] = [
            'amount' => $refundedAmount,
            'comment' => $comment,
            'refunded_at' => (new \DateTimeImmutable('now'))->format(\DateTimeImmutable::ATOM),
        ];
        $payment->setDetails($details);

        $this->logger->info('alma.refund.completed', [
            'payment_id' => $payment->getId(),
            'order_number' => $order->getNumber(),
            'alma_payment_id' => $almaPaymentId,
            'mode' => $persistedMode,
            'amount' => $refundedAmount,
        ]);
    }
}
