<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    /**
     * The base every stored rate is quoted against.
     */
    public const BASE = 'USD';

    protected $fillable = [
        'base_code',
        'quote_code',
        'rate',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * The latest stored rate for every currency, keyed by code.
     *
     * @return array<string, float>
     */
    public static function latestTable(string $base = self::BASE): array
    {
        return static::query()
            ->where('base_code', $base)
            ->pluck('rate', 'quote_code')
            ->map(fn ($rate) => (float) $rate)
            ->all();
    }

    /**
     * When each pair was last written by a sync, keyed by currency code.
     *
     * This is `updated_at` rather than `fetched_at` on purpose. `fetched_at`
     * is upstream's own publication time and can predate our write by hours,
     * which would let an override typed at 00:30 wrongly outlive the 01:00 run.
     *
     * @return array<string, \Illuminate\Support\Carbon>
     */
    public static function syncedAtByCode(string $base = self::BASE): array
    {
        return static::query()
            ->where('base_code', $base)
            ->pluck('updated_at', 'quote_code')
            ->mapWithKeys(fn ($timestamp, $code) => [
                strtoupper((string) $code) => \Illuminate\Support\Carbon::parse($timestamp),
            ])
            ->all();
    }

    /**
     * When a sync last successfully wrote any rate, or null if none ever has.
     *
     * Distinct from {@see static::lastFetchedAt()}: this answers "did the job
     * run?", where that answers "how current are the numbers?". A widening gap
     * between the two means the schedule has stopped running.
     */
    public static function lastSyncedAt(string $base = self::BASE): ?\Illuminate\Support\Carbon
    {
        $timestamp = static::query()
            ->where('base_code', $base)
            ->max('updated_at');

        return $timestamp ? \Illuminate\Support\Carbon::parse($timestamp) : null;
    }

    /**
     * When the stored rates were last refreshed, or null if there are none.
     */
    public static function lastFetchedAt(string $base = self::BASE): ?\Illuminate\Support\Carbon
    {
        $timestamp = static::query()
            ->where('base_code', $base)
            ->max('fetched_at');

        return $timestamp ? \Illuminate\Support\Carbon::parse($timestamp) : null;
    }
}
