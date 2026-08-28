<?php

declare(strict_types=1);

namespace Alma\Sylius\Payment;

use Alma\Client\Application\Helper\WebhookHelper;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Authenticates an incoming Alma IPN by HMAC-SHA256 over the request's query
 * parameters, with the active-mode API key as secret. Delegates the actual
 * crypto to {@see WebhookHelper::verifySignature()} from the PHP client v3
 * (`hash_hmac('sha256', sorted-params-string, $secret)` → base64 url-safe).
 *
 * Default-deny posture: if the active-mode API key is missing, the verifier
 * MUST return false rather than skip the check. The caller (controller) MUST
 * map that to an HTTP 401 — same outcome as a tampered signature, so a
 * misconfigured plugin doesn't accidentally trust unsigned traffic.
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
    public function verify(string $signature, array $params): bool
    {
        if ($signature === '') {
            return false;
        }

        $mode = $this->configurationResolver->getConfiguration()->getApiMode();
        $apiKey = $this->configurationResolver->getApiKeyByMode($mode);
        if ($apiKey === null) {
            $this->logger->error('alma.ipn.signature_verify_failed', [
                'reason' => 'missing_api_key',
                'mode' => $mode,
            ]);

            return false;
        }

        return WebhookHelper::verifySignature($signature, $params, $apiKey);
    }
}
