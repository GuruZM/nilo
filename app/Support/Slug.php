<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slug
{
    public static function uniqueCompanySlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (\App\Models\Company::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}