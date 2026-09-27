<?php

declare(strict_types=1);

namespace Tests\Alma\Sylius\Security;

use Alma\Sylius\Security\ApiKeyCipher;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class ApiKeyCipherTest extends TestCase
{
    private const APP_SECRET = 'unit-test-kernel-secret';

    public function testRoundtripPreservesTheApiKey(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);
        $apiKey = str_repeat('k', 32);

        $blob = $cipher->encrypt($apiKey);

        $this->assertStringStartsWith('v1:', $blob);
        $this->assertStringNotContainsString($apiKey, $blob);
        $this->assertSame($apiKey, $cipher->decrypt($blob));
    }

    public function testRoundtripSurvivesUnicodeAndLargePayloads(): void
    {
        $cipher = new ApiKeyCipher('clé-secrète-🔐');
        $apiKey = str_repeat('k', 512) . '-ünïcode';

        $this->assertSame($apiKey, $cipher->decrypt($cipher->encrypt($apiKey)));
    }

    public function testEncryptsTwiceIntoDifferentBlobs(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);

        $first = $cipher->encrypt('same-plain-key');
        $second = $cipher->encrypt('same-plain-key');

        $this->assertNotSame($first, $second, 'Each blob must use its own random nonce.');
    }

    public function testRejectsATamperedCiphertext(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);
        $blob = $cipher->encrypt('key-under-test');
        $tampered = substr($blob, 0, -2) . ($blob[-1] === 'A' ? 'B' : 'A');

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt($tampered);
    }

    public function testRejectsAWrongApplicationSecret(): void
    {
        $blob = (new ApiKeyCipher(self::APP_SECRET))->encrypt('key-under-test');

        $this->expectException(\RuntimeException::class);
        (new ApiKeyCipher('another-secret'))->decrypt($blob);
    }

    public function testRejectsMalformedBase64Payload(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);

        $this->expectException(\InvalidArgumentException::class);
        $cipher->decrypt('v1:%%%not-base64%%%');
    }

    public function testRejectsPayloadsShorterThanANonce(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);
        $short = 'v1:' . base64_encode('ab');

        $this->expectException(\InvalidArgumentException::class);
        $cipher->decrypt($short);
    }

    public function testRejectsUnknownVersionPrefix(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);

        $this->expectException(\InvalidArgumentException::class);
        $cipher->decrypt('v2:' . base64_encode(str_repeat('x', 40)));
    }

    public function testRejectsAnEmptyApplicationSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiKeyCipher('');
    }

    #[TestDox('isEncrypted() recognizes its own blobs only')]
    public function testIsEncryptedDetection(): void
    {
        $cipher = new ApiKeyCipher(self::APP_SECRET);

        $this->assertTrue($cipher->isEncrypted($cipher->encrypt('another-key')));
        $this->assertFalse($cipher->isEncrypted('another-key'));
    }
}
