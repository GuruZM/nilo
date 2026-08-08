<?php

namespace App\Services\Dpo\Data;

final readonly class DpoCreateTokenResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $transactionToken,
        public string $transactionRef,
        public array $raw,
    ) {}
}
