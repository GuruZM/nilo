<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

/**
 * The outcome of one sync attempt.
 *
 * A failure carries the provider's message rather than throwing, because both
 * callers treat a failed sync as a reportable non-event: the stored rates stay
 * exactly as they were.
 */
class ExchangeRateSyncResult
{
    protected function __construct(
        public readonly bool $successful,
        public readonly int $count = 0,
        public readonly string $base = ExchangeRate::BASE,
        public readonly ?Carbon $fetchedAt = null,
        public readonly ?string $error = null,
    ) {}

    public static function succeeded(int $count, string $base, Carbon $fetchedAt): self
    {
        return new self(true, $count, $base, $fetchedAt);
    }

    public static function failed(string $error): self
    {
        return new self(false, error: $error);
    }
}
