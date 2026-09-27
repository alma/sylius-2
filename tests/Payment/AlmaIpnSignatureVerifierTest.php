<?php

declare(strict_types=1);

namespace Tests\Alma\Sylius\Payment;

use Alma\Sylius\Entity\AlmaConfiguration;
use Alma\Sylius\Payment\AlmaIpnSignatureVerifier;
use Alma\Sylius\Resolver\AlmaPaymentMethodResolver;
use PHPUnit\Framework\TestCase;

/**
 * The signature scheme is reimplemented here on purpose: the test must not
 * call the SDK helper it is supposed to police. ksort by param name,
 * "param=urlencoded(value)" pairs joined with "&", HMAC-SHA256 over the raw
 * digest, base64 then URL-safe transliteration without padding.
 */
final class AlmaIpnSignatureVerifierTest extends TestCase
{
    private const MODE_SECRET = 'unit-mode-secret-0123456789abcdef';

    public function testAcceptsASignatureComputedOverSortedParams(): void
    {
        $verifier = $this->verifier(withApiKey: true);

        // Params deliberately out of order: the signer sorts, the verifier must too.
        $signature = $this->sign([
            'payment' => 'pay_000042',
            'mode' => 'test',
            'state' => 'paid',
        ], self::MODE_SECRET);

        $this->assertTrue($verifier->verify($signature, [
            'payment' => 'pay_000042',
            'mode' => 'test',
            'state' => 'paid',
        ]));
    }

    public function testRejectsATamperedParam(): void
    {
        $verifier = $this->verifier(withApiKey: true);
        $signature = $this->sign(['state' => 'paid', 'payment' => 'pay_000042'], self::MODE_SECRET);

        $this->assertFalse($verifier->verify($signature, [
            'state' => 'paid',
            'payment' => 'pay_000043',
        ]));
    }

    public function testRejectsASignatureFromADifferentSecret(): void
    {
        $verifier = $this->verifier(withApiKey: true);
        $signature = $this->sign(['payment' => 'pay_000042'], 'another-secret-0123456789');

        $this->assertFalse($verifier->verify($signature, ['payment' => 'pay_000042']));
    }

    public function testDefaultDeniesAnEmptySignature(): void
    {
        $verifier = $this->verifier(withApiKey: true);

        $this->assertFalse($verifier->verify('', ['payment' => 'pay_000042']));
    }

    public function testDefaultDeniesWhenTheActiveModeKeyIsMissing(): void
    {
        $verifier = $this->verifier(withApiKey: false);
        $signature = $this->sign(['payment' => 'pay_000042'], self::MODE_SECRET);

        // A misconfigured plugin must fail closed, same outcome as tampering.
        $this->assertFalse($verifier->verify($signature, ['payment' => 'pay_000042']));
    }

    private function verifier(bool $withApiKey): AlmaIpnSignatureVerifier
    {
        $resolver = $this->createMock(AlmaPaymentMethodResolver::class);
        $resolver->method('getConfiguration')->willReturn(new AlmaConfiguration());
        $resolver->method('getApiKeyByMode')->willReturn(
            $withApiKey ? self::MODE_SECRET : null
        );

        return new AlmaIpnSignatureVerifier($resolver);
    }

    /**
     * @param array<string, string> $params
     */
    private function sign(array $params, string $secret): string
    {
        ksort($params, SORT_STRING);
        $pairs = [];
        foreach ($params as $param => $value) {
            $pairs[] = sprintf('%s=%s', $param, rawurlencode($value));
        }

        $digest = hash_hmac('sha256', implode('&', $pairs), $secret, true);

        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }
}
