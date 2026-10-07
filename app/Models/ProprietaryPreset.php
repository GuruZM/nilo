<?php

namespace App\Models;

use App\Enums\TemplatePreset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A template preset one company holds exclusively. A preset with no row here
 * is open to every company.
 */
class ProprietaryPreset extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'preset',
        'company_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preset' => TemplatePreset::class,
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The presets another company holds, which this one may not use. A null
     * company is nobody's, so every proprietary preset is closed to it.
     *
     * @return list<string>
     */
    public static function lockedFor(?int $companyId): array
    {
        return static::query()
            ->when($companyId, fn ($query) => $query->where('company_id', '!=', $companyId))
            ->pluck('preset')
            ->map(fn (TemplatePreset $preset): string => $preset->value)
            ->all();
    }
}
