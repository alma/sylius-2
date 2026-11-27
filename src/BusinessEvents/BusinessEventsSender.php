<?php

declare(strict_types=1);

namespace Alma\Sylius\BusinessEvents;

use Alma\Client\Application\DTO\MerchantBusinessEvent\CartInitiatedBusinessEventDto;
use Alma\Client\Application\DTO\MerchantBusinessEvent\OrderConfirmedBusinessEventDto;
use Alma\Client\Application\Endpoint\MerchantEndpoint;
use Alma\Sylius\Api\MerchantEndpointFactoryInterface;
use Alma\Sylius\Checkout\PlanGroup;
use Alma\Sylius\Payment\AlmaPaidPaymentFinder;
use Alma\Sylius\Payment\AlmaPaymentDetailsKeys;
use Alma\Sylius\Repository\AlmaBusinessEventRepository;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;

class BusinessEventsSender
{
    public const EVENT_CART_INITIATED = 'cart_initiated';
    public const EVENT_ORDER_CONFIRMED = 'order_confirmed';

    public function __construct(
        private readonly AlmaBusinessEventRepository $repository,
        private readonly MerchantEndpointFactoryInterface $merchantEndpointFactory,
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly AlmaPaidPaymentFinder $paidPaymentFinder,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function sendCartInitiated(OrderInterface $cart): void
    {
        $cartId = $cart->getId();
        if ($cartId === null) {
            return;
        }

        try {
            if ($this->repository->isCartReported($cartId)) {
                return;
            }

            $this->merchantEndpoint($cart)->sendCartInitiatedBusinessEvent(new CartInitiatedBusinessEventDto((string) $cartId));
            $this->repository->markCartReported($cartId, new \DateTimeImmutable());
        } catch (\Throwable $exception) {
            $this->logFailure(self::EVENT_CART_INITIATED, ['cart_id' => $cartId], $exception);

            return;
        }

        $this->logger->info('alma.business_events.cart_initiated_sent', ['cart_id' => $cartId]);
    }

    public function sendOrderConfirmed(OrderInterface $order): void
    {
        $orderId = $order->getId();
        if ($orderId === null) {
            return;
        }

        $context = ['order_number' => $order->getNumber()];

        try {
            $wasBnplEligible = $this->repository->claimOrderConfirmation($orderId, new \DateTimeImmutable());
            if ($wasBnplEligible === null) {
                return;
            }

            $almaPayment = $this->paidPaymentFinder->find($order);
            $almaPaymentId = '';
            $planGroup = null;
            if ($almaPayment !== null) {
                $details = $almaPayment->getDetails();
                $almaPaymentId = (string) $details[AlmaPaymentDetailsKeys::PAYMENT_ID];
                $planGroup = PlanGroup::fromPlanKey((string) ($details[AlmaPaymentDetailsKeys::FEE_PLAN_KEY] ?? ''));
                $context['payment_id'] = $almaPaymentId;
            }

            $this->merchantEndpoint($order)->sendOrderConfirmedBusinessEvent(new OrderConfirmedBusinessEventDto(
                $planGroup === PlanGroup::PayNow,
                $planGroup !== null && $planGroup !== PlanGroup::PayNow,
                $wasBnplEligible,
                (string) $order->getNumber(),
                (string) $orderId,
                $almaPaymentId,
            ));
        } catch (\Throwable $exception) {
            $this->logFailure(self::EVENT_ORDER_CONFIRMED, $context, $exception);

            return;
        }

        $this->logger->info('alma.business_events.order_confirmed_sent', $context);
    }

    private function merchantEndpoint(OrderInterface $order): MerchantEndpoint
    {
        $mode = $this->configurationResolver->getConfiguration($order->getChannel())->getApiMode();
        $apiKey = $this->configurationResolver->getApiKeyByMode($order->getChannel(), $mode);
        if ($apiKey === null) {
            throw new \LogicException(sprintf('No API key configured for the active mode "%s".', $mode));
        }

        return $this->merchantEndpointFactory->make($apiKey, $mode);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logFailure(string $event, array $context, \Throwable $exception): void
    {
        $this->logger->warning('alma.business_events.send_failed', [
            'event' => $event,
            ...$context,
            'exception' => $exception::class,
        ]);
    }
}
