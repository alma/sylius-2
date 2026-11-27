<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Application\DTO\CartDto;
use Alma\Client\Application\DTO\CartItemDto;
use Alma\Client\Application\DTO\CustomerDto;
use Alma\Client\Application\DTO\OrderDto;
use Alma\Client\Application\DTO\PaymentDto;
use Alma\Client\Application\DTO\RequestedProductDto;
use Alma\Sylius\Eligibility\SyliusAddressMapper;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the three DTOs sent to PaymentEndpoint::create() from the Sylius
 * payment + order + the customer-selected fee plan key. The mapping decisions
 * (locale fallback, customData keys, MVP B2C defaults, etc.) follow the
 * Requirement "Payment is created with cart, customer, and order data" in
 * docs/specs/payment-creation/spec.md.
 *
 * Field names on the DTOs are sourced from the Alma PHP client v3
 * (Alma\Client\Application\DTO\*) — the spec describes intent, the client
 * describes the technical contract.
 */
class AlmaPaymentRequestBuilder
{
    public function __construct(
        private readonly SyliusAddressMapper $addressMapper,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function build(PaymentInterface $payment, string $planKey, bool $inPage = false): AlmaPaymentRequest
    {
        $feePlan = FeePlanKey::parse($planKey);

        return $this->assemble($payment, $inPage, static function (PaymentDto $paymentDto) use ($feePlan): void {
            $paymentDto
                ->setInstallmentsCount($feePlan->installmentsCount)
                ->setDeferredDays($feePlan->deferredDays)
                ->setDeferredMonths($feePlan->deferredMonths)
            ;
        });
    }

    /**
     * @param list<string> $planKeys
     */
    public function buildUnified(PaymentInterface $payment, array $planKeys): AlmaPaymentRequest
    {
        return $this->assemble($payment, false, static function (PaymentDto $paymentDto) use ($planKeys): void {
            foreach ($planKeys as $planKey) {
                $feePlan = FeePlanKey::parse($planKey);
                $product = new RequestedProductDto($feePlan->installmentsCount);
                if ($feePlan->deferredDays > 0) {
                    $product->setDeferredDays($feePlan->deferredDays);
                }
                $paymentDto->addRequestedProduct($product);
            }
        });
    }

    /**
     * @param \Closure(PaymentDto): void $applyPlans
     */
    private function assemble(PaymentInterface $payment, bool $inPage, \Closure $applyPlans): AlmaPaymentRequest
    {
        $order = $payment->getOrder();
        if ($order === null) {
            throw new \LogicException('Cannot build Alma payment request: payment has no order.');
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $email = $order->getCustomer()?->getEmail();

        $paymentDto = new PaymentDto($order->getTotal());
        $applyPlans($paymentDto);
        $paymentDto
            ->setOrigin($inPage ? PaymentDto::ORIGIN_ONLINE_IN_PAGE : PaymentDto::ORIGIN_ONLINE)
            ->setReturnUrl($this->absoluteUrl('alma_sylius_payment_return'))
            ->setIpnCallbackUrl($this->absoluteUrl('alma_sylius_payment_ipn'))
            ->setCustomData([
                'order_id' => $order->getNumber(),
                'order_internal_id' => $order->getId(),
            ])
        ;

        if ($inPage) {
            // The In-Page modal sends a rejected payment to its failure return URL.
            // No customer cancel URL: Alma's checkout opens it inside the modal
            // (« back to the shop » on a rejection offering to pay at once),
            // which leaves the modal open over the order page.
            $paymentDto->setFailureReturnUrl($this->absoluteUrl('alma_sylius_payment_cancel'));
        } else {
            $paymentDto->setCustomerCancelUrl($this->absoluteUrl('alma_sylius_payment_cancel'));
        }

        $locale = $this->resolveLocale($order);
        if ($locale !== null) {
            $paymentDto->setLocale($locale);
        }

        $billingDto = $this->addressMapper->toAlmaAddress($billingAddress, $email);
        if ($billingDto !== null) {
            $paymentDto->setBillingAddress($billingDto);
        }
        $shippingDto = $this->addressMapper->toAlmaAddress($shippingAddress, $email);
        if ($shippingDto !== null) {
            $paymentDto->setShippingAddress($shippingDto);
        }

        $cartDto = new CartDto();
        foreach ($order->getItems() as $item) {
            $cartItem = new CartItemDto(
                $item->getQuantity(),
                $item->getTotal(),
                (string) $item->getProductName(),
            );
            $cartItem->setUnitPrice($item->getUnitPrice());
            $variant = $item->getVariant();
            if ($variant !== null && $variant->getCode() !== null) {
                $cartItem->setSku($variant->getCode());
            }
            $cartDto->addItem($cartItem);
        }
        $paymentDto->setCart($cartDto);

        $orderDto = new OrderDto();
        if ($order->getNumber() !== null) {
            $orderDto->setMerchantReference($order->getNumber());
        }
        $merchantUrl = $this->maybeAbsoluteUrlForRoute('sylius_admin_order_show', ['id' => $order->getId()]);
        if ($merchantUrl !== null) {
            $orderDto->setMerchantUrl($merchantUrl);
        }
        $customerUrl = $this->maybeAbsoluteUrlForRoute('sylius_shop_order_show', ['tokenValue' => $order->getTokenValue()]);
        if ($customerUrl !== null) {
            $orderDto->setCustomerUrl($customerUrl);
        }

        $customerDto = $this->buildCustomerDto($order, $billingAddress);

        return new AlmaPaymentRequest($paymentDto, $orderDto, $customerDto);
    }

    private function buildCustomerDto(OrderInterface $order, ?AddressInterface $billing): CustomerDto
    {
        $customer = $order->getCustomer();

        $firstName = $customer?->getFirstName() ?? $billing?->getFirstName() ?? '';
        $lastName = $customer?->getLastName() ?? $billing?->getLastName() ?? '';
        $email = $customer?->getEmail() ?? '';

        $dto = new CustomerDto();
        $dto->setFirstName($firstName);
        $dto->setLastName($lastName);
        $dto->setEmail($email);
        $dto->setIsBusiness(false);

        $phone = $customer?->getPhoneNumber() ?? $billing?->getPhoneNumber();
        if ($phone !== null && $phone !== '') {
            $dto->setPhone($phone);
        }

        $billingDto = $this->addressMapper->toAlmaAddress($billing, $email !== '' ? $email : null);
        if ($billingDto !== null) {
            $dto->addAddress($billingDto);
        }

        return $dto;
    }

    private function resolveLocale(OrderInterface $order): ?string
    {
        $orderLocale = $order->getLocaleCode();
        if ($this->isValidLocale($orderLocale)) {
            return $orderLocale;
        }

        $channel = $order->getChannel();
        $channelDefault = $channel?->getDefaultLocale()?->getCode();

        return $this->isValidLocale($channelDefault) ? $channelDefault : null;
    }

    private function isValidLocale(?string $locale): bool
    {
        return $locale !== null && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) === 1;
    }

    /**
     * Bare URL with no query parameter — Alma appends `?pid=<alma_payment_id>`
     * when it invokes the callback (cf. "Three callback URLs are registered").
     */
    private function absoluteUrl(string $route): string
    {
        return $this->urlGenerator->generate(
            $route,
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function maybeAbsoluteUrlForRoute(string $route, array $parameters): ?string
    {
        try {
            return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return null;
        }
    }
}
