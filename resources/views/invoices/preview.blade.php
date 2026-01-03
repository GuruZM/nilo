<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Invoice Preview</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:24px; }
        .page { width: 794px; margin: 0 auto; background:#fff; border:1px solid #ddd; border-radius:16px; padding:32px; }
        table { width:100%; border-collapse: collapse; margin-top:16px; }
        th, td { padding:10px; border-bottom:1px solid #eee; font-size: 13px; }
        th { text-align:left; background:#fafafa; }
        .right { text-align:right; }
        .muted { color:#666; font-size:12px; }
        .totals { margin-top:16px; }
        .totals div { display:flex; justify-content:space-between; padding:6px 0; font-size:13px; }
        .totals .strong { font-weight:bold; font-size:14px; }
    </style>
</head>
<body>
    <div class="page">
        <h2 style="margin:0;">INVOICE (PREVIEW)</h2>
        <p class="muted" style="margin-top:6px;">
            {{ $company->name ?? 'Company' }} • {{ $client->name }}
        </p>

        <div style="display:flex; justify-content:space-between; margin-top:16px;">
            <div>
                <div class="muted">Bill To</div>
                <div><strong>{{ $client->name }}</strong></div>
                @if($client->contact_person)<div class="muted">Attn: {{ $client->contact_person }}</div>@endif
                @if($client->email)<div class="muted">{{ $client->email }}</div>@endif
            </div>

            <div class="right">
                <div class="muted">Issue Date</div>
                <div>{{ $invoice['issue_date'] }}</div>
                @if($invoice['due_date'])
                    <div class="muted" style="margin-top:8px;">Due Date</div>
                    <div>{{ $invoice['due_date'] }}</div>
                @endif
                <div class="muted" style="margin-top:8px;">Status</div>
                <div><strong>{{ strtoupper($invoice['status']) }}</strong></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $it)
                    <tr>
                        <td>{{ $it['description'] }}</td>
                        <td class="right">{{ number_format((float)$it['quantity'], 2) }}</td>
                        <td class="right">{{ number_format((float)$it['unit_price'], 2) }}</td>
                        <td class="right">{{ number_format((float)$it['line_total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div><span>Subtotal</span><span>{{ number_format((float)$invoice['subtotal'], 2) }}</span></div>
            <div><span>Line Discounts</span><span>{{ number_format((float)$invoice['discount_total'], 2) }}</span></div>
            <div><span>Overall Discount</span><span>{{ number_format((float)$invoice['overall_discount'], 2) }}</span></div>
            <div><span>Tax</span><span>{{ number_format((float)$invoice['tax_total'], 2) }}</span></div>
            <div class="strong"><span>Total</span><span>{{ number_format((float)$invoice['total'], 2) }}</span></div>
        </div>

        @if(!empty($invoice['notes']))
            <div style="margin-top:18px;">
                <div class="muted">Notes</div>
                <div style="white-space:pre-wrap;">{{ $invoice['notes'] }}</div>
            </div>
        @endif

        <div style="margin-top:18px;">
            <div class="muted">Terms</div>
            <div style="white-space:pre-wrap;">{{ $invoice['terms'] ?? ($template->terms_html ?? '') }}</div>
        </div>

        <div class="muted" style="text-align:center; margin-top:28px;">
            {{ $template->footer_html ?? '' }}
        </div>
    </div>
</body>
</html>