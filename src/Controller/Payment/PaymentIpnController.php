<?php

declare(strict_types=1);

namespace Alma\Sylius\Controller\Payment;

use Alma\Sylius\Payment\AlmaFraudChecker;
use Alma\Sylius\Payment\AlmaFraudFlagger;
use Alma\Sylius\Payment\AlmaIpnSignatureVerifier;
use Alma\Sylius\Payment\AlmaPaymentCompletionHelper;
use Alma\Sylius\Payment\AlmaPaymentResolver;
use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public IPN endpoint hit by Alma server-to-server to notify of a payment
 * state change. Response codes follow Alma's retry policy:
 *
 * - 404: the `pid` is missing or matches no Alma payment reference, checked
 *   before the signature since the signing key is the one of the order's
 *   channel; also when the payment cannot be fetched from Alma or does not
 *   match the order;
 * - 401: signature missing or invalid;
 * - 200: the IPN was processed (completed, idempotent no-op, or flagged);
 * - 500: unexpected exceptions, left to Symfony's default handler.
 */
#[Route(
    '/alma/payment/ipn',
    name: 'alma_sylius_payment_ipn',
    methods: ['GET'],
)]
final class PaymentIpnController
{
    private const SIGNATURE_HEADER = 'X-Alma-Signature';
    private const SIGNATURE_QUERY_PARAM = 'signature';

    public function __construct(
        private readonly AlmaIpnSignatureVerifier $signatureVerifier,
        private readonly AlmaPaymentReferenceRepository $referenceRepository,
        private readonly AlmaPaymentResolver $paymentResolver,
        private readonly AlmaFraudChecker $fraudChecker,
        private readonly AlmaFraudFlagger $fraudFlagger,
        private readonly AlmaPaymentCompletionHelper $completionHelper,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $rawPid = $request->query->get('pid');
        $pid = \is_string($rawPid) && $rawPid !== '' ? $rawPid : null;
        $reference = $pid === null ? null : $this->referenceRepository->findOneByAlmaPaymentId($pid);
        if ($reference === null) {
            $this->logger->warning('alma.ipn.payment_not_resolved', [
                'payment_id' => $pid,
            ]);

            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $signature = (string) $request->headers->get(self::SIGNATURE_HEADER, '');
        if ($signature === '') {
            $this->logger->warning('alma.ipn.signature_missing');

            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        $params = $request->query->all();
        unset($params[self::SIGNATURE_QUERY_PARAM]);

        if (!$this->signatureVerifier->verify($signature, $params, $reference)) {
            $this->logger->warning('alma.ipn.signature_invalid');

            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        $this->logger->info('alma.ipn.received', [
            'payment_id' => $pid,
        ]);

        $resolved = $this->paymentResolver->resolve($pid);
        if ($resolved === null) {
            $this->logger->warning('alma.ipn.payment_not_resolved', [
                'payment_id' => $pid,
            ]);

            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $checkResult = $this->fraudChecker->check($resolved);
        if (!$checkResult->passed) {
            $this->fraudFlagger->flag($resolved, $checkResult);

            return new JsonResponse(['status' => 'ok', 'pid' => $pid]);
        }

        $this->completionHelper->complete($resolved);

        return new JsonResponse(['status' => 'ok', 'pid' => $pid]);
    }
}
