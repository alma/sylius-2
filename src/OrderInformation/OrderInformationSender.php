<?php

declare(strict_types=1);

namespace Alma\Sylius\OrderInformation;

use Alma\Client\Application\Endpoint\PaymentEndpoint;
use Alma\Client\Domain\Entity\Order as AlmaOrder;
use Alma\Sylius\Api\OrderEndpointFactoryInterface;
use Alma\Sylius\Api\PaymentEndpointFactoryInterface;
use Alma\Sylius\Entity\AlmaPaymentReference;
use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\OrderShippingStates;
use Sylius\Component\Payment\Model\PaymentInterface;

class OrderInformationSender
{
    public const KIND_STATUS = 'status';
    public const KIND_SHIPMENT = 'shipment';

    private const PAID_PAYMENT_STATES = [
        PaymentInterface::STATE_COMPLETED,
        PaymentInterface::STATE_REFUNDED,
    ];

    public function __construct(
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
        private readonly PaymentEndpointFactoryInterface $paymentEndpointFactory,
        private readonly OrderEndpointFactoryInterface $orderEndpointFactory,
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly OrderStatusLabelResolver $labelResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function sendStatus(OrderInterface $order, string $state): void
    {
        $reference = null;

        try {
            $reference = $this->findPaidReference($order);
            if ($reference === null) {
                return;
            }

            $this->paymentEndpoint($reference)->addOrderStatusByMerchantOrderReference(
                $reference->getAlmaPaymentId(),
                (string) $order->getNumber(),
                $this->labelResolver->resolve($state, $order),
                $order->getShippingState() === OrderShippingStates::STATE_SHIPPED,
            );
        } catch (\Throwable $exception) {
            $this->logFailure($order, $reference, self::KIND_STATUS, $exception);

            return;
        }

        $this->logSent($order, $reference, self::KIND_STATUS);
    }

    public function sendShipment(ShipmentInterface $shipment): void
    {
        $trackingNumber = trim((string) $shipment->getTracking());
        $order = $shipment->getOrder();
        if ($trackingNumber === '' || !$order instanceof OrderInterface) {
            return;
        }

        $reference = null;

        try {
            $reference = $this->findPaidReference($order);
            if ($reference === null) {
                return;
            }

            $almaOrder = $this->almaOrderFor($this->paymentEndpoint($reference), $reference, (string) $order->getNumber());
            $this->orderEndpointFactory
                ->make($this->apiKey($reference), $reference->getAlmaMode())
                ->addTracking($almaOrder->getExternalId(), $this->carrierName($shipment, $order), $trackingNumber, null);
        } catch (\Throwable $exception) {
            $this->logFailure($order, $reference, self::KIND_SHIPMENT, $exception);

            return;
        }

        $this->logSent($order, $reference, self::KIND_SHIPMENT);
    }

    private function findPaidReference(OrderInterface $order): ?AlmaPaymentReference
    {
        foreach ($this->referenceRepository->findByOrder($order) as $reference) {
            if (\in_array($reference->getSyliusPayment()->getState(), self::PAID_PAYMENT_STATES, true)) {
                return $reference;
            }
        }

        return null;
    }

    private function paymentEndpoint(AlmaPaymentReference $reference): PaymentEndpoint
    {
        return $this->paymentEndpointFactory->make($this->apiKey($reference), $reference->getAlmaMode());
    }

    private function apiKey(AlmaPaymentReference $reference): string
    {
        $apiKey = $this->configurationResolver->getApiKeyOfOrder($reference->getSyliusOrder(), $reference->getAlmaMode());
        if ($apiKey === null) {
            throw new \LogicException(sprintf('No API key configured for the persisted mode "%s".', $reference->getAlmaMode()));
        }

        return $apiKey;
    }

    /**
     * Payment creation links an Alma order carrying the order number; one is
     * linked here only for payments that have none. `$overwrite` stays false:
     * Alma's payments API replaces every order of the payment otherwise.
     */
    private function almaOrderFor(PaymentEndpoint $paymentEndpoint, AlmaPaymentReference $reference, string $orderNumber): AlmaOrder
    {
        foreach ($paymentEndpoint->fetch($reference->getAlmaPaymentId())->getOrders() as $almaOrder) {
            if ($almaOrder->getMerchantReference() === $orderNumber) {
                return $almaOrder;
            }
        }

        return $paymentEndpoint->addOrder($reference->getAlmaPaymentId(), ['merchant_reference' => $orderNumber], false);
    }

    /**
     * Reads the translation collection directly: Sylius' getTranslation()
     * adds an empty translation to the shipping method when the locale has none.
     */
    private function carrierName(ShipmentInterface $shipment, OrderInterface $order): string
    {
        $method = $shipment->getMethod();
        $locale = $order->getChannel()?->getDefaultLocale()?->getCode();
        $name = $locale !== null ? $method?->getTranslations()->get($locale)?->getName() : null;

        return $name !== null && $name !== '' ? $name : (string) $method?->getCode();
    }

    private function logSent(OrderInterface $order, ?AlmaPaymentReference $reference, string $kind): void
    {
        $this->logger->info('alma.order_information.sent', [
            'order_number' => $order->getNumber(),
            'payment_id' => $reference?->getAlmaPaymentId(),
            'kind' => $kind,
        ]);
    }

    private function logFailure(OrderInterface $order, ?AlmaPaymentReference $reference, string $kind, \Throwable $exception): void
    {
        $this->logger->warning('alma.order_information.send_failed', [
            'order_number' => $order->getNumber(),
            'payment_id' => $reference?->getAlmaPaymentId(),
            'kind' => $kind,
            'exception' => $exception::class,
        ]);
    }
}
