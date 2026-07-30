<?php

namespace App\Support;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Sequential per-company document numbers.
 *
 * The next number comes from the highest one already issued, not from a row
 * count. Counting looks equivalent and is not: delete two of three receipts and
 * the count-based sequence walks straight back over a number that still exists,
 * violating the unique index and throwing mid-transaction. Receipts are the
 * first document type here with a delete path, so that stopped being
 * theoretical.
 *
 * There is still no sequence table, so two concurrent inserts can read the same
 * highest number. Callers must run this inside the same transaction as the
 * insert; the unique index is what ultimately arbitrates.
 */
class DocumentNumber
{
    private const PAD = 6;

    /**
     * The model and the type must correspond — passing `Receipt::class` with
     * `DocumentType::CreditNote` scans the right rows and stamps the wrong
     * prefix, which nothing downstream would catch.
     *
     * `$column` is the column holding the printed number. It is `number` on
     * every document except a payment, which calls its own `receipt_number`.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function nextFor(
        string $modelClass,
        int $companyId,
        DocumentType $type,
        string $column = 'number',
    ): string {
        $prefix = $type->numberPrefix();

        /**
         * Zero padding to a fixed width makes lexicographic order match numeric
         * order, so the highest number is one ordered query rather than a scan
         * and a cast — and it behaves identically on sqlite and postgres.
         */
        $highest = $modelClass::query()
            ->where('company_id', $companyId)
            ->where($column, 'like', $prefix.'-%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = $highest === null
            ? 1
            : ((int) substr((string) $highest, strlen($prefix) + 1)) + 1;

        return $prefix.'-'.str_pad((string) $sequence, self::PAD, '0', STR_PAD_LEFT);
    }
}
