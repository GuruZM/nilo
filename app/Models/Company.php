<?php

namespace App\Models;

use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'type',
        'currency_code',
        'email',
        'phone',
        'tpin',
        'address',
        'logo_path',
        'primary_color',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
        ];
    }

    /**
     * The fields that make up a complete company profile. Required and optional
     * fields both count, so the score reflects the whole record rather than
     * only what was left blank at creation.
     *
     * @var list<string>
     */
    public const PROFILE_FIELDS = [
        'name',
        'type',
        'currency_code',
        'email',
        'phone',
        'tpin',
        'address',
        'logo_path',
        'primary_color',
    ];

    /**
     * How much of the company profile has been filled in.
     *
     * @return array{filled: int, total: int, percent: int}
     */
    public function profileCompletion(): array
    {
        $total = count(self::PROFILE_FIELDS);

        $filled = collect(self::PROFILE_FIELDS)
            ->filter(function (string $field): bool {
                $value = $this->getAttribute($field);

                if ($value instanceof CompanyType) {
                    return true;
                }

                return is_string($value) && trim($value) !== '';
            })
            ->count();

        return [
            'filled' => $filled,
            'total' => $total,
            'percent' => (int) round($filled / $total * 100),
        ];
    }

    /**
     * @return array{filled: int, total: int, percent: int}
     */
    public function getProfileCompletionAttribute(): array
    {
        return $this->profileCompletion();
    }

    public function getLogoUrlAttribute(): ?string
    {
        $logoPath = $this->logo_path;

        if (! is_string($logoPath) || $logoPath === '') {
            return null;
        }

        if (Str::startsWith($logoPath, ['http://', 'https://', '//'])) {
            return $logoPath;
        }

        $normalizedPath = ltrim($logoPath, '/');

        if (Str::startsWith($normalizedPath, 'storage/')) {
            $normalizedPath = Str::after($normalizedPath, 'storage/');
        }

        if (Str::startsWith($normalizedPath, 'public/')) {
            $normalizedPath = Str::after($normalizedPath, 'public/');
        }

        if ($normalizedPath === '') {
            return null;
        }

        return '/storage/'.$normalizedPath;
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user')->withPivot('role')->withTimestamps();
    }

    /**
     * The owner of the company.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The currency this company bills in.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * The currency new documents for a company should default to. The company's
     * own currency wins so that switching the global currency toggle can never
     * change what a company bills in.
     */
    public static function defaultCurrencyCodeFor(?int $companyId, ?string $fallback = null): string
    {
        $companyCurrencyCode = $companyId
            ? static::query()->whereKey($companyId)->value('currency_code')
            : null;

        return strtoupper((string) ($companyCurrencyCode ?: $fallback ?: 'ZMW'));
    }

    /**
     * The clients of the company.
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    /**
     * The invoices of the company.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The quotations of the company.
     */
    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * The compliance documents uploaded for this company.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * CompanyUser pivot records for this company.
     */
    public function companyUsers()
    {
        return $this->hasMany(CompanyUser::class);
    }
}
