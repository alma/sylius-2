<?php

declare(strict_types=1);

namespace Alma\Sylius\Controller\Payment;

use Alma\Sylius\Payment\AlmaPaymentCancellationHelper;
use Alma\Sylius\Payment\AlmaPaymentResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Cancel route hit by the customer's browser after they trigger the cancel
 * button on Alma's hosted page. Aligned on the Sylius native pattern: only the
 * Sylius Payment is transitioned to `cancelled`; the Order and the active cart
 * are not touched, so the customer can retry payment (Alma or another method)
 * from `sylius_shop_order_show` — same target Sylius PayPal/Mollie redirect to
 * via Bundle\PayumBundle\Action\ResolveNextRouteAction.
 */
#[Route(
    '/alma/payment/cancel',
    name: 'alma_sylius_payment_cancel',
    methods: ['GET'],
)]
final class PaymentCancelController
{
    use FlashBagHelperTrait;

    public function __construct(
        private readonly AlmaPaymentResolver $paymentResolver,
        private readonly AlmaPaymentCancellationHelper $cancellationHelper,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $pid = $request->query->get('pid');
        if (!is_string($pid) || $pid === '') {
            return $this->redirectGenericError();
        }

        $resolved = $this->paymentResolver->resolve($pid);
        if ($resolved === null) {
            return $this->redirectGenericError();
        }

        $this->cancellationHelper->cancel($resolved);
        $this->addFlash('info', 'alma_sylius.flash.payment_cancelled');

        return new RedirectResponse($this->urlGenerator->generate(
            'sylius_shop_order_show',
            ['tokenValue' => $resolved->order->getTokenValue()],
        ));
    }

    private function redirectGenericError(): RedirectResponse
    {
        $this->addFlash('error', 'alma_sylius.flash.payment_cancel_failed');

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
    }
}
