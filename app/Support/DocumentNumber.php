<?php

namespace App\Support;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Sequential per-company document numbers.
 *
 * This counts existing rows rather than holding a sequence table, matching how
 * invoices and quotations have always numbered themselves. Callers must run it
 * inside the same transaction as the insert.
 */
class DocumentNumber
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function nextFor(string $modelClass, int $companyId, DocumentType $type): string
    {
        $sequence = $modelClass::query()
            ->where('company_id', $companyId)
            ->count() + 1;

        return $type->numberPrefix().'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
