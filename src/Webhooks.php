<?php

declare(strict_types=1);

namespace Shieldz;

class Webhooks
{
    /**
     * Verify a Shieldz webhook signature. Pass the raw request body.
     * Header: `t=<unix>,v1=<hex>[,v1=<hex>]`; signed payload is `<t>.<body>`.
     *
     * @param array{tolerance?: int, now?: int} $opts
     * @throws SignatureVerificationError
     */
    public static function verifySignature(string $rawBody, string $signatureHeader, string $signingSecret, array $opts = []): bool
    {
        if ($signatureHeader === '') {
            throw new SignatureVerificationError('missing signature header');
        }
        if ($signingSecret === '') {
            throw new SignatureVerificationError('missing signing secret');
        }

        $tolerance = $opts['tolerance'] ?? 300;
        $now = $opts['now'] ?? time();

        $t = null;
        $sigs = [];
        foreach (array_map('trim', explode(',', $signatureHeader)) as $part) {
            if (str_starts_with($part, 't=')) {
                $t = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $sigs[] = substr($part, 3);
            }
        }
        if ($t === null || count($sigs) === 0) {
            throw new SignatureVerificationError('malformed signature header');
        }
        if (!is_numeric($t) || abs($now - (int) $t) > $tolerance) {
            throw new SignatureVerificationError('timestamp outside tolerance');
        }

        $expected = hash_hmac('sha256', $t . '.' . $rawBody, $signingSecret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        throw new SignatureVerificationError('no matching signature');
    }

    /**
     * Verify the signature and return the decoded event.
     *
     * @param array{tolerance?: int, now?: int} $opts
     * @throws SignatureVerificationError
     */
    public static function constructEvent(string $rawBody, string $signatureHeader, string $signingSecret, array $opts = []): array
    {
        self::verifySignature($rawBody, $signatureHeader, $signingSecret, $opts);
        return json_decode($rawBody, true);
    }
}
