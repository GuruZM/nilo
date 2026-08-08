<?php

namespace App\Services\Dpo;

use RuntimeException;

class DpoException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(string $message, public readonly array $response = [])
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(string $operation, array $response): self
    {
        $explanation = $response['ResultExplanation'] ?? $response['body'] ?? 'Unknown error';

        return new self("DPO {$operation} request failed: {$explanation}", $response);
    }
}
