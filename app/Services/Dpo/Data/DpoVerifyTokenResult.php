<?php

namespace App\Services\Dpo\Data;

final readonly class DpoVerifyTokenResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $resultCode,
        public string $resultExplanation,
        public string $status,
        public array $raw,
    ) {}

    /**
     * Whether DPO has stopped working on this transaction, either way.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['failed', 'cancelled', 'expired'], true);
    }
}
