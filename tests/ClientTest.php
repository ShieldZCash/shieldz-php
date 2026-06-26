<?php

declare(strict_types=1);

namespace Shieldz\Tests;

use PHPUnit\Framework\TestCase;
use Shieldz\Invoices;
use Shieldz\Shieldz;
use Shieldz\ShieldzError;

final class ClientTest extends TestCase
{
    public function testRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Shieldz('');
    }

    public function testWiresInvoices(): void
    {
        $client = new Shieldz('sk_test');
        $this->assertInstanceOf(Invoices::class, $client->invoices);
    }

    public function testErrorShape(): void
    {
        $e = new ShieldzError(400, ['type' => 'invalid_request', 'code' => 'invalid_amount', 'message' => 'too small', 'param' => 'amount_usd_cents'], 'ray-1');
        $this->assertSame(400, $e->status);
        $this->assertSame('invalid_amount', $e->code);
        $this->assertSame('amount_usd_cents', $e->param);
        $this->assertSame('ray-1', $e->requestId);
    }
}
