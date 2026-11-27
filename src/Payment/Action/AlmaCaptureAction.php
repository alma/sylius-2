<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment\Action;

use Alma\Client\Application\Exception\Endpoint\PaymentEndpointException;
use Alma\Sylius\Api\PaymentEndpointFactoryInterface;
use Alma\Sylius\Entity\AlmaPaymentReference;
use Alma\Sylius\InPage\InPageModal;
use Alma\Sylius\Payment\AlmaPaymentDetailsKeys;
use Alma\Sylius\Payment\AlmaPaymentRequestBuilder;
use Alma\Sylius\Payment\AlreadyPaidOrder;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Alma\Sylius\Twig\AlmaCheckoutEligibilityProvider;
use Doctrine\ORM\EntityManagerInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Calls Alma's PaymentEndpoint::create() at place-order time (or when the
 * order page's payment form is submitted), persists the references on the
 * Sylius Payment's details JSON and on an indexed AlmaPaymentReference, then
 * replies with a Payum HttpRedirect that Sylius converts into a 302:
 *
 * - off-site flow: to the Alma hosted payment page;
 * - In-Page flow: back to the order page, which opens the Alma modal for the
 *   payment just created (cf. specs-sylius/in-page).
 *
 * An In-Page attempt always creates a new Alma payment, recorded alongside
 * the earlier ones of the order; the Sylius Payment stays `new` so the order
 * page can offer Alma or another method again.
 *
 * On PaymentEndpointException the customer is sent back to the order page
 * with a generic flash message. Off-site, the Sylius Payment is transitioned
 * to `failed` first; In-Page, it stays `new`.
 */
final class AlmaCaptureAction implements ActionInterface
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly AlmaPaymentRequestBuilder $requestBuilder,
        private readonly PaymentEndpointFactoryInterface $endpointFactory,
        private readonly StateMachineInterface $stateMachine,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly AlmaCheckoutEligibilityProvider $eligibilityProvider,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $payment = $request->getModel();
        if (!$payment instanceof SyliusPaymentInterface) {
            return;
        }

        $order = $payment->getOrder();
        if ($order !== null && AlreadyPaidOrder::is($order)) {
            throw new HttpRedirect($this->urlGenerator->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        $channel = $order?->getChannel();
        $configuration = $this->configurationResolver->getConfiguration($channel);
        $unified = $configuration->isUnifiedPaymentButtonEnabled();
        $inPage = $configuration->isInPageEnabled() && !$unified;

        $details = $payment->getDetails();
        if (!$inPage && isset($details[AlmaPaymentDetailsKeys::PAYMENT_ID], $details[AlmaPaymentDetailsKeys::PAYMENT_URL])) {
            // Already created off-site — likely a retry of the same place-order.
            // Redirect to the existing Alma hosted page without re-calling the API.
            throw new HttpRedirect($details[AlmaPaymentDetailsKeys::PAYMENT_URL]);
        }

        $mode = $configuration->getApiMode();
        $eligiblePlanKeys = [];

        if ($unified) {
            $planKey = null;
            $eligiblePlanKeys = $this->eligiblePlanKeys($payment);
            if ($eligiblePlanKeys === []) {
                $this->logger->warning('alma.payment.no_eligible_plan', [
                    'mode' => $mode,
                    'order' => $order?->getId(),
                    'amount' => $payment->getAmount(),
                ]);
                $this->handleCreateFailure($payment, false);

                throw new \LogicException('Cannot create Alma payment: no plan is eligible for the order.');
            }
        } else {
            $planKey = $details[AlmaPaymentDetailsKeys::FEE_PLAN_KEY] ?? null;
            if (!is_string($planKey) || $planKey === '') {
                throw new \LogicException('Cannot create Alma payment: missing alma_fee_plan_key in payment details.');
            }
        }

        $apiKey = $this->configurationResolver->getApiKeyByMode($channel, $mode);
        if ($apiKey === null) {
            throw new \LogicException(sprintf('Cannot create Alma payment: active mode "%s" has no API key.', $mode));
        }

        $almaRequest = $planKey === null
            ? $this->requestBuilder->buildUnified($payment, $eligiblePlanKeys)
            : $this->requestBuilder->build($payment, $planKey, $inPage);

        try {
            $almaPayment = $this->endpointFactory->make($apiKey, $mode)->create(
                $almaRequest->payment,
                $almaRequest->order,
                $almaRequest->customer,
            );
        } catch (PaymentEndpointException $e) {
            $this->logger->error('alma.payment.create_failure', [
                'mode' => $mode,
                'order' => $order?->getId(),
                'amount' => $payment->getAmount(),
                'in_page' => $inPage,
                'exception' => $e::class,
            ]);

            $this->handleCreateFailure($payment, $inPage);

            throw $e;
        }

        $details[AlmaPaymentDetailsKeys::PAYMENT_ID] = $almaPayment->getId();
        $details[AlmaPaymentDetailsKeys::MODE] = $mode;
        if ($inPage) {
            unset($details[AlmaPaymentDetailsKeys::PAYMENT_URL]);
        } else {
            $details[AlmaPaymentDetailsKeys::PAYMENT_URL] = $almaPayment->getUrl();
        }
        if ($planKey === null) {
            unset($details[AlmaPaymentDetailsKeys::FEE_PLAN_KEY]);
        }
        $payment->setDetails($details);

        // Indexed lookup (pid → Payment + Order + persisted mode) consumed by
        // AlmaPaymentResolver for return/IPN/refund, one row per Alma payment.
        $reference = new AlmaPaymentReference(
            $almaPayment->getId(),
            $mode,
            $payment,
            $payment->getOrder(),
            $planKey,
            $inPage ? AlmaPaymentReference::ORIGIN_IN_PAGE : AlmaPaymentReference::ORIGIN_OFF_SITE,
        );
        $this->entityManager->persist($reference);
        $this->entityManager->flush();

        if ($inPage) {
            throw new HttpRedirect($this->urlGenerator->generate('sylius_shop_order_show', ['tokenValue' => $order?->getTokenValue(), InPageModal::QUERY_PARAMETER => $almaPayment->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        throw new HttpRedirect($almaPayment->getUrl());
    }

    public function supports($request): bool
    {
        return $request instanceof Capture && $request->getModel() instanceof SyliusPaymentInterface;
    }

    /**
     * @return list<string>
     */
    private function eligiblePlanKeys(SyliusPaymentInterface $payment): array
    {
        $order = $payment->getOrder();
        if ($order === null) {
            return [];
        }

        $planKeys = [];
        foreach ($this->eligibilityProvider->getEligibilities($order) as $planKey => $eligibility) {
            if ($eligibility->isEligible()) {
                $planKeys[] = (string) $planKey;
            }
        }

        return $planKeys;
    }

    /**
     * The order itself stays in its post-cart state — no rollback (cf. spec
     * "Failure leaves the order in pending state"). Off-site, the Sylius
     * Payment is marked `failed` so the order page offers a retry; In-Page, it
     * stays `new` so the customer retries on the same Sylius Payment.
     */
    private function handleCreateFailure(SyliusPaymentInterface $payment, bool $inPage): void
    {
        if (!$inPage && $this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        }

        $request = $this->requestStack->getMainRequest();
        if ($request !== null && $request->hasSession()) {
            $session = $request->getSession();
            if ($session instanceof Session) {
                $session->getFlashBag()->add('error', 'alma_sylius.flash.payment_creation_failed');
            }
        }

        $order = $payment->getOrder();
        $tokenValue = $order?->getTokenValue();
        if ($tokenValue === null) {
            return;
        }

        $url = $this->urlGenerator->generate(
            'sylius_shop_order_show',
            ['tokenValue' => $tokenValue],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        throw new HttpRedirect($url);
    }
}
