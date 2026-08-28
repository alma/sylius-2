<?php

declare(strict_types=1);

namespace Alma\Sylius\Controller\Payment;

use Alma\Sylius\Payment\AlmaFraudChecker;
use Alma\Sylius\Payment\AlmaFraudFlagger;
use Alma\Sylius\Payment\AlmaPaymentCompletionHelper;
use Alma\Sylius\Payment\AlmaPaymentResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Return route hit by the customer's browser after a payment attempt on Alma's
 * hosted page. Honours the customer-return spec :
 *
 * - Server-to-server fetch is the source of truth (cf. AlmaPaymentResolver).
 * - The CMS order is retrieved via `custom_data.order_id` echoed by Alma.
 * - No technical detail is surfaced to the customer (generic flash on error,
 *   redirect to a Sylius page on both success and error paths).
 *
 * On the happy path the route applies the idempotent payment.complete
 * transition (E3) and redirects the customer to `sylius_shop_order_thank_you`
 * — same target as Sylius PayPal/Mollie plugins. Downstream side-effects —
 * order_payment → paid, inventory release, and merchant-configured invoice/
 * email — cascade natively through Sylius 2.0 workflow listeners.
 */
#[Route(
    '/alma/payment/return',
    name: 'alma_sylius_payment_return',
    methods: ['GET'],
)]
final class PaymentReturnController
{
    use FlashBagHelperTrait;

    public function __construct(
        private readonly AlmaPaymentResolver $paymentResolver,
        private readonly AlmaFraudChecker $fraudChecker,
        private readonly AlmaFraudFlagger $fraudFlagger,
        private readonly AlmaPaymentCompletionHelper $completionHelper,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $pid = $request->query->get('pid');
        if (!is_string($pid) || $pid === '') {
            return $this->redirectGenericError('alma_sylius.flash.payment_return_failed');
        }

        $resolved = $this->paymentResolver->resolve($pid);
        if ($resolved === null) {
            return $this->redirectGenericError('alma_sylius.flash.payment_return_failed');
        }

        $checkResult = $this->fraudChecker->check($resolved);
        if (!$checkResult->passed) {
            $this->fraudFlagger->flag($resolved, $checkResult);

            return $this->redirectGenericError('alma_sylius.flash.payment_fraud_detected');
        }

        if ($this->completionHelper->complete($resolved)) {
            $this->addFlash('success', 'alma_sylius.flash.payment_paid');
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_order_thank_you'));
    }

    private function redirectGenericError(string $flashKey): RedirectResponse
    {
        $this->addFlash('error', $flashKey);

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
    }
}
