<?php

declare(strict_types=1);

namespace Shieldz\Tests;

use PHPUnit\Framework\TestCase;
use Shieldz\SignatureVerificationError;
use Shieldz\Webhooks;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';
    private string $body = '{"type":"invoice.paid","id":"inv_1"}';

    private function sign(string $body, string $secret = self::SECRET, ?int $t = null): string
    {
        $t = $t ?? time();
        $hex = hash_hmac('sha256', $t . '.' . $body, $secret);
        return "t={$t},v1={$hex}";
    }

    public function testValid(): void
    {
        $this->assertTrue(Webhooks::verifySignature($this->body, $this->sign($this->body), self::SECRET));
        $event = Webhooks::constructEvent($this->body, $this->sign($this->body), self::SECRET);
        $this->assertSame('invoice.paid', $event['type']);
    }

    public function testRotationMultipleV1(): void
    {
        $t = time();
        $good = explode('v1=', $this->sign($this->body, self::SECRET, $t))[1];
        $header = "t={$t},v1=" . str_repeat('0', 64) . ",v1={$good}";
        $this->assertTrue(Webhooks::verifySignature($this->body, $header, self::SECRET));
    }

    public function testTampered(): void
    {
        $this->expectException(SignatureVerificationError::class);
        Webhooks::verifySignature($this->body . ' ', $this->sign($this->body), self::SECRET);
    }

    public function testWrongSecret(): void
    {
        $this->expectException(SignatureVerificationError::class);
        Webhooks::verifySignature($this->body, $this->sign($this->body), 'whsec_other');
    }

    public function testStale(): void
    {
        $this->expectException(SignatureVerificationError::class);
        Webhooks::verifySignature($this->body, $this->sign($this->body, self::SECRET, time() - 3600), self::SECRET);
    }

    public function testCustomNowTolerance(): void
    {
        $t = 1000000;
        $this->assertTrue(
            Webhooks::verifySignature($this->body, $this->sign($this->body, self::SECRET, $t), self::SECRET, ['now' => $t + 10, 'tolerance' => 60])
        );
    }

    /**
     * @dataProvider malformedHeaders
     */
    public function testMalformed(string $header): void
    {
        $this->expectException(SignatureVerificationError::class);
        Webhooks::verifySignature($this->body, $header, self::SECRET);
    }

    public static function malformedHeaders(): array
    {
        return [[''], ['garbage'], ['v1=abc']];
    }
}
