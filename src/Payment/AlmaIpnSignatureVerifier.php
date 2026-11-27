<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Application\Helper\WebhookHelper;
use Alma\Sylius\Entity\AlmaPaymentReference;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Authenticates an incoming Alma IPN by HMAC-SHA256 over the request's query
 * parameters, with the API key of the order's channel for the mode persisted
 * on the payment as secret (cf. specs-sylius/module-configuration). Delegates
 * the crypto to {@see WebhookHelper::verifySignature()} from the PHP client v3.
 *
 * Default-deny: a missing key fails the check, which the controller maps to
 * an HTTP 401.
 */
class AlmaIpnSignatureVerifier
{
    public function __construct(
        private readonly AlmaPaymentMethodResolver $configurationResolver,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, string> $params query params used for signing,
     *                                      excluding the `signature` key
     */
    public function verify(string $signature, array $params, AlmaPaymentReference $reference): bool
    {
        if ($signature === '') {
            return false;
        }

        $mode = $reference->getAlmaMode();
        $apiKey = $this->configurationResolver->getApiKeyOfOrder($reference->getSyliusOrder(), $mode);
        if ($apiKey === null) {
            $this->logger->error('alma.ipn.signature_verify_failed', [
                'reason' => 'missing_api_key',
                'mode' => $mode,
                'payment_id' => $reference->getAlmaPaymentId(),
            ]);

            return false;
        }

        return WebhookHelper::verifySignature($signature, $params, $apiKey);
    }
}
