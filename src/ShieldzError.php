<?php

declare(strict_types=1);

namespace Shieldz;

/** Thrown for any non-2xx response (status 0 for connection/timeout errors). */
class ShieldzError extends \Exception
{
    public int $status;
    public string $type;
    /** Machine-readable error code (e.g. "invalid_amount"). Named `errorCode`
     *  because \Exception already reserves the integer `$code`. */
    public string $errorCode;
    public ?string $param;
    public ?string $requestId;

    public function __construct(int $status, array $body = [], ?string $requestId = null)
    {
        parent::__construct($body['message'] ?? "Shieldz API error (HTTP {$status})", $status);
        $this->status = $status;
        $this->type = $body['type'] ?? 'api_error';
        $this->errorCode = $body['code'] ?? 'unknown';
        $this->param = $body['param'] ?? null;
        $this->requestId = $requestId;
    }
}
