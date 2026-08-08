<?php

namespace App\Support;

/**
 * Totals for any priced document — invoice, quotation, credit note or
 * purchase order.
 *
 * Prices are entered tax-inclusive: what you type on a line is what the
 * counterparty pays for it. Discounts come off that gross figure, then the tax
 * is carved back out of what remains, so `subtotal` is the net (tax exclusive)
 * amount and `total` is the gross (tax inclusive) one.
 *
 *   items_gross   Σ qty × price           5000.00
 *   − discounts                              0.00
 *   = total       gross, tax inclusive    5000.00
 *     subtotal    total ÷ (1 + rate)      4310.34
 *     tax_total   total − subtotal         689.66
 *
 * Tax is derived by subtraction rather than multiplication so subtotal and tax
 * always add back to exactly the total, with no rounding drift.
 *
 * This lived as a private method copied into four controllers. One copy means
 * the mobile API and the web app cannot quietly start producing different
 * numbers for the same input.
 */
class DocumentTotals
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, items_gross: float, subtotal: float, line_discount_total: float, document_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    public static function compute(array $items, float $documentDiscount, float $taxPercent): array
    {
        $itemsGross = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($items as $i => $row) {
            $qty = (float) $row['quantity'];
            $price = (float) $row['unit_price'];
            $discount = (float) ($row['discount'] ?? 0);

            $lineBase = $qty * $price;

            $items[$i]['discount'] = $discount;
            $items[$i]['tax'] = 0; // tax is charged on the document, not the line
            $items[$i]['line_total'] = max(0, $lineBase - $discount);
            $items[$i]['sort_order'] = $i;

            $itemsGross += $lineBase;
            $lineDiscountTotal += $discount;
        }

        $discountTotal = $lineDiscountTotal + $documentDiscount;

        $total = round(max(0, $itemsGross - $discountTotal), 2);
        $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
        $taxTotal = round($total - $subtotal, 2);

        return [
            'items' => $items,
            'items_gross' => $itemsGross,
            'subtotal' => $subtotal,
            'line_discount_total' => $lineDiscountTotal,
            'document_discount' => $documentDiscount,
            'discount_total' => $discountTotal,
            'tax_percent' => $taxPercent,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }
}
