# shieldz/shieldz

[![CI](https://github.com/ShieldZCash/shieldz-php/actions/workflows/ci.yml/badge.svg)](https://github.com/ShieldZCash/shieldz-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/shieldz/shieldz.svg)](https://packagist.org/packages/shieldz/shieldz)
[![PHP](https://img.shields.io/packagist/php-v/shieldz/shieldz.svg)](https://packagist.org/packages/shieldz/shieldz)

Official PHP SDK for [**Shieldz**](https://shieldz.cash) — non-custodial crypto payments with **$0 fees**.

Accept **USDC/USDT** across Base, Arbitrum, Optimism, Polygon, and Ethereum, plus native **Bitcoin** and shielded **Zcash**. Funds settle straight to your own wallet — Shieldz never holds them, and never asks for your keys.

## Install

```bash
composer require shieldz/shieldz
```

Requires PHP 8.0+ with the `curl` and `json` extensions. No other dependencies.

## Quickstart

```php
use Shieldz\Shieldz;

$shieldz = new Shieldz(getenv('SHIELDZ_API_KEY'));

$invoice = $shieldz->invoices->create([
    'amount_usd_cents' => 5000,        // $50.00
    'memo' => 'Order #1234',
    'metadata' => ['order_id' => '1234'],
]);

echo $invoice['pay_url']; // send your customer to the hosted checkout
```

### Retrieve, list & auto-paginate

```php
$inv = $shieldz->invoices->retrieve('Qgvz8WQw0mnv2M8');

$page = $shieldz->invoices->list(['limit' => 20, 'status' => 'paid']);

foreach ($shieldz->invoices->listAll(['status' => 'paid']) as $invoice) {
    echo $invoice['id'], "\n";
}
```

Retryable POSTs get an auto `idempotency_key` so a retried create can't duplicate; pass your own to tie it to an order.

## Webhooks

Verify against the **raw request body**:

```php
use Shieldz\Webhooks;
use Shieldz\SignatureVerificationError;

$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_SHIELDZ_SIGNATURE'] ?? '';

try {
    $event = Webhooks::constructEvent($raw, $sig, getenv('SHIELDZ_WEBHOOK_SECRET'));
} catch (SignatureVerificationError $e) {
    http_response_code(400);
    exit;
}

if ($event['type'] === 'invoice.paid') {
    // fulfill — dedupe on the X-Shieldz-Delivery header (at-least-once)
}
http_response_code(200);
```

`Webhooks::verifySignature(...)` returns a bool / throws if you just want the check. During the 24h after a secret rotation the header carries both signatures and either matches.

## Errors

Any non-2xx throws `Shieldz\ShieldzError` with `->status`, `->type`, `->errorCode`, `->param`, `->requestId` (the machine code is `->errorCode` because `\Exception` reserves the integer `->code`, which we set to the HTTP status).

## Links

- Docs / API quickstart: https://shieldz.cash/docs
- Is it safe? (non-custodial proof): https://shieldz.cash/verify
- Node SDK: https://github.com/ShieldZCash/shieldz-sdk · Python: https://github.com/ShieldZCash/shieldz-python · Rust: https://github.com/ShieldZCash/shieldz-rust

## License

MIT © Deniz Yanbollu / Shieldz
