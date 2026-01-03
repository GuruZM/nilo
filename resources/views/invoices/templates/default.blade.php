 {{-- resources/views/invoices/templates/default.blade.php --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ data_get($invoice, 'number', '') }}</title>
    <style>
        /* DomPDF-safe styling */
        @page { margin: 28px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .h1 { font-size: 18px; font-weight: 700; margin: 0; }
        .h2 { font-size: 12px; font-weight: 700; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .hr { border-top: 1px solid #e5e7eb; margin: 16px 0; }

        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .items th {
            background: #f3f4f6;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .items td {
            padding: 10px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .items tr:last-child td { border-bottom: 1px solid #e5e7eb; }

        .totals { margin-top: 14px; }
        .totals td { padding: 6px 0; }
        .totals .label { color: #6b7280; }
        .totals .grand { font-size: 14px; font-weight: 700; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #fff7ed;
            color: #b45309;
        }
        .badge.paid { background: #ecfdf5; color: #047857; }

        .box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; }
        .grid { width: 100%; }
        .col { width: 50%; vertical-align: top; }
        .mt { margin-top: 12px; }

        .footer { margin-top: 22px; font-size: 11px; color: #6b7280; text-align: center; }
        .pre { white-space: pre-wrap; }
    </style>
</head>
<body>

@php
    // Supports both preview payload array and saved Invoice model
    $invoiceNumber = is_array($invoice) ? ($invoice['number'] ?? '—') : ($invoice->number ?? '—');
    $status = is_array($invoice) ? ($invoice['status'] ?? 'pending') : ($invoice->status ?? 'pending');

    $currencyCode = $currency->code ?? (is_array($invoice) ? ($invoice['currency_code'] ?? 'ZMW') : ($invoice->currency_code ?? 'ZMW'));
    $symbol = $currency->symbol ?? '';
    $precision = (int)($currency->precision ?? 2);

    $money = function($n) use ($currencyCode, $symbol, $precision) {
        $val = is_numeric($n) ? (float)$n : 0;
        $fmt = number_format($val, $precision, '.', ',');
        $prefix = trim($symbol) !== '' ? $symbol : $currencyCode;
        return $prefix . ' ' . $fmt;
    };

    $issueDate = is_array($invoice) ? ($invoice['issue_date'] ?? null) : ($invoice->issue_date ?? null);
    $dueDate   = is_array($invoice) ? ($invoice['due_date'] ?? null) : ($invoice->due_date ?? null);

    $subtotal = is_array($invoice) ? ($invoice['subtotal'] ?? 0) : ($invoice->subtotal ?? 0);
    $discountTotal = is_array($invoice) ? ($invoice['discount_total'] ?? 0) : ($invoice->discount_total ?? 0);
    $invoiceDiscount = is_array($invoice)
        ? ($invoice['overall_discount'] ?? ($invoice['invoice_discount'] ?? 0))
        : ($invoice->invoice_discount ?? 0);

    $taxTotal = is_array($invoice) ? ($invoice['tax_total'] ?? 0) : ($invoice->tax_total ?? 0);
    $total = is_array($invoice) ? ($invoice['total'] ?? 0) : ($invoice->total ?? 0);

    $title = is_array($invoice) ? ($invoice['title'] ?? null) : ($invoice->title ?? null);
    $reference = is_array($invoice) ? ($invoice['reference'] ?? null) : ($invoice->reference ?? null);
    $notes = is_array($invoice) ? ($invoice['notes'] ?? null) : ($invoice->notes ?? null);
    $terms = is_array($invoice) ? ($invoice['terms'] ?? null) : ($invoice->terms ?? null);

    // items can be array rows or models
    $rows = $items ?? (is_array($invoice) ? ($invoice['items'] ?? []) : ($invoice->items ?? []));
@endphp

{{-- HEADER --}}
<table class="grid">
    <tr>
        <td class="col">
            <div class="h1">{{ $company->name ?? 'Company' }}</div>
            <div class="muted" style="margin-top:4px;">
                {{-- Add address/phone/email later --}}
            </div>
        </td>
        <td class="col right">
            <div class="h1" style="margin:0;">INVOICE</div>
            <div style="margin-top:6px;">
                <span class="badge {{ $status === 'paid' ? 'paid' : '' }}">
                    {{ strtoupper($status) }}
                </span>
            </div>
        </td>
    </tr>
</table>

<div class="hr"></div>

{{-- META --}}
<table class="grid meta">
    <tr>
        <td class="col">
            <div class="h2">Bill To</div>
            <div style="margin-top:6px;" class="bold">{{ $client->name ?? '—' }}</div>
            @if(!empty($client->email))
                <div class="muted">{{ $client->email }}</div>
            @endif
            @if(!empty($client->contact_person))
                <div class="muted">Attn: {{ $client->contact_person }}</div>
            @endif
        </td>
        <td class="col right">
            <table style="width:100%;">
                <tr>
                    <td class="muted right">Number</td>
                    <td class="right bold" style="width:180px;">{{ $invoiceNumber }}</td>
                </tr>
                @if($title)
                <tr>
                    <td class="muted right">Title</td>
                    <td class="right">{{ $title }}</td>
                </tr>
                @endif
                @if($reference)
                <tr>
                    <td class="muted right">Reference</td>
                    <td class="right">{{ $reference }}</td>
                </tr>
                @endif
                <tr>
                    <td class="muted right">Issue date</td>
                    <td class="right">{{ $issueDate ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="muted right">Due date</td>
                    <td class="right">{{ $dueDate ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="muted right">Currency</td>
                    <td class="right">{{ $currencyCode }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="hr"></div>

{{-- ITEMS --}}
<table class="items">
    <thead>
    <tr>
        <th style="text-align:left;">Description</th>
        <th class="right" style="width:70px;">Qty</th>
        <th class="right" style="width:110px;">Unit</th>
        <th class="right" style="width:120px;">Price</th>
        <th class="right" style="width:120px;">Line total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $r)
        @php
            $desc = is_array($r) ? ($r['description'] ?? '') : ($r->description ?? '');
            $qty  = is_array($r) ? ($r['quantity'] ?? 0) : ($r->quantity ?? 0);
            $unit = is_array($r) ? ($r['unit'] ?? '') : ($r->unit ?? '');
            $price = is_array($r) ? ($r['unit_price'] ?? 0) : ($r->unit_price ?? 0);

            // In previewNew we pass computed line_total; for saved invoice you already have it
            $lineTotal = is_array($r)
                ? ($r['line_total'] ?? ((float)$qty * (float)$price))
                : ($r->line_total ?? ((float)$qty * (float)$price));
        @endphp
        <tr>
            <td>
                <div class="bold">{{ $desc ?: '—' }}</div>
                @if(!empty($unit))
                    <div class="muted" style="margin-top:2px;">Unit: {{ $unit }}</div>
                @endif
            </td>
            <td class="right">{{ number_format((float)$qty, 2, '.', ',') }}</td>
            <td class="right">{{ $unit ?: '—' }}</td>
            <td class="right">{{ $money($price) }}</td>
            <td class="right bold">{{ $money($lineTotal) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- TOTALS --}}
<table class="totals">
    <tr>
        <td class="label right" style="width:70%;">Subtotal</td>
        <td class="right" style="width:30%;">{{ $money($subtotal) }}</td>
    </tr>
    <tr>
        <td class="label right">Discounts</td>
        <td class="right">- {{ $money($discountTotal) }}</td>
    </tr>
    @if((float)$invoiceDiscount > 0)
        <tr>
            <td class="label right">Invoice discount</td>
            <td class="right">- {{ $money($invoiceDiscount) }}</td>
        </tr>
    @endif
    <tr>
        <td class="label right">Tax</td>
        <td class="right">{{ $money($taxTotal) }}</td>
    </tr>
    <tr>
        <td class="right bold grand">Total</td>
        <td class="right bold grand">{{ $money($total) }}</td>
    </tr>
</table>

{{-- NOTES / TERMS --}}
@if($notes)
    <div class="mt box">
        <div class="h2">Notes</div>
        <div class="pre" style="margin-top:6px;">{{ $notes }}</div>
    </div>
@endif

@if($terms)
    <div class="mt box">
        <div class="h2">Terms</div>
        <div class="pre" style="margin-top:6px;">{{ $terms }}</div>
    </div>
@endif

<div class="footer">
    {{ $template->footer_html ?? '' }}
</div>

</body>
</html>