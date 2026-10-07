<?php

namespace App\Enums;

use App\Models\ProprietaryPreset;

/**
 * The designs a document template can be built on. The builder draws each one
 * and the shared blade prints it, so a new case needs both before it is offered.
 */
enum TemplatePreset: string
{
    case WavePremium = 'wave_premium';
    case Meridian = 'meridian';
    case ModernMinimal = 'modern_minimal';
    case ClassicBusiness = 'classic_business';
    case BoldHeader = 'bold_header';

    public function label(): string
    {
        return match ($this) {
            self::WavePremium => 'Wave Premium',
            self::Meridian => 'Meridian',
            self::ModernMinimal => 'Modern Minimal',
            self::ClassicBusiness => 'Classic Business',
            self::BoldHeader => 'Bold Header',
        };
    }

    /**
     * What a template falls back to when its own preset is out of reach.
     */
    public static function fallback(): self
    {
        return self::WavePremium;
    }

    /**
     * Every preset except those another company holds exclusively.
     *
     * @return list<self>
     */
    public static function usableBy(?int $companyId): array
    {
        $locked = ProprietaryPreset::lockedFor($companyId);

        return array_values(array_filter(
            self::cases(),
            fn (self $preset): bool => ! in_array($preset->value, $locked, true),
        ));
    }
}
