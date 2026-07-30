<?php

namespace App\Enums;

enum CompanyType: string
{
    case Services = 'services';
    case Products = 'products';

    public function label(): string
    {
        return match ($this) {
            self::Services => 'Services business',
            self::Products => 'Products business',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Services => 'Bill for time, projects and deliverables.',
            self::Products => 'Sell physical or stocked goods.',
        };
    }
}
