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
 * Synchronously calls Alma's PaymentEndpoint::refund() server-to-server, then
 * persists a local refund record on the Sylius Payment's details JSON.
 *
 * At MVP this helper supports **full refund only**, aligned on the Sylius
 * native state machine (transition `sylius_payment.refund` is terminal). The
 * RefundDto sent to Alma always omits `amount`. Partial / multi refunds would
 * require sylius/refund-plugin — out of MVP scope.
 *
 * Pessimistic failure policy: callers MUST invoke this helper BEFORE the CMS
 * commits its refund. Any PaymentEndpointException propagates out — the
 * caller's transaction is aborted, the CMS-side refund is not committed.
 *
 * The persisted Alma mode (from {@see \Alma\Sylius\Entity\AlmaPaymentReference})
 * is used to pick the API key, not the currently active global mode. This
 * upholds the contract acted in the `payment-creation` spec.
 */
class AlmaRefundHelper
{
    public const FULL_COMMENT = 'Full refund from Sylius plugin';

    public function __construct(
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
        private readonly PaymentEndpointFactoryInterface $endpointFactory,
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function refund(SyliusPaymentInterface $payment): void
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
        $apiKey = $this->configurationResolver->getApiKeyByMode($persistedMode);
        if ($apiKey === null) {
            throw new \LogicException(sprintf('Cannot refund Alma payment: persisted mode "%s" has no API key configured.', $persistedMode));
        }

        $order = $reference->getSyliusOrder();
        $dto = (new RefundDto())
            ->setMerchantReference((string) $order->getNumber())
            ->setComment(self::FULL_COMMENT);
        // No setAmount(): MVP = full refund only, conformément à Sylius native
        // state machine (transition refund terminale).

        $this->endpointFactory->make($apiKey, $persistedMode)->refund($almaPaymentId, $dto);

        $details[AlmaPaymentDetailsKeys::REFUND_AMOUNT] = $order->getTotal();
        $details[AlmaPaymentDetailsKeys::REFUND_COMMENT] = self::FULL_COMMENT;
        $details[AlmaPaymentDetailsKeys::REFUND_AT] = (new \DateTimeImmutable('now'))->format(\DateTimeImmutable::ATOM);
        $payment->setDetails($details);

        $this->logger->info('alma.refund.completed', [
            'payment_id' => $payment->getId(),
            'order_number' => $order->getNumber(),
            'alma_payment_id' => $almaPaymentId,
            'mode' => $persistedMode,
        ]);
    }
}
