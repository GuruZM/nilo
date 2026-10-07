{{-- resources/views/invoices/templates/presets/meridian.blade.php --}}
@php
    /**
     * Meridian: a coloured side panel carrying the dates and the client, a QR
     * tile, an oversized title and a carded item table.
     *
     * Included from default.blade.php with the same view data. Unlike the
     * default sheet it is laid out with tables and absolute positioning only,
     * because the emailed PDF goes through DomPDF, which has no flexbox or
     * grid — the browser preview, print view and attachment all match.
     */
    $type = ($documentType ?? null) instanceof \App\Enums\DocumentType
        ? $documentType
        : (\App\Enums\DocumentType::tryFrom((string) ($documentType ?? 'invoice'))
            ?? \App\Enums\DocumentType::Invoice);

    $s = is_array($settings ?? null) ? $settings : [];
    $brand = (array) ($s['brand'] ?? []);
    $content = (array) ($s['content'] ?? []);
    $visibility = (array) ($s['visibility'] ?? []);
    $tableStyle = (string) data_get($s, 'layout.table', 'striped');
    $density = (string) data_get($s, 'layout.density', 'normal');

    $ink = (string) ($brand['primary'] ?? '#1F2937');
    $accent = (string) ($brand['accent'] ?? '#5570A2');
    $panel = (string) ($brand['header'] ?? $ink);
    $fontKey = (string) ($brand['font'] ?? 'Inter');

    $showLogo = (bool) ($visibility['show_logo'] ?? true);
    $showQr = (bool) ($visibility['show_qr'] ?? true);
    $showClientEmail = (bool) ($visibility['show_client_email'] ?? true);
    $showContactPerson = (bool) ($visibility['show_contact_person'] ?? true);
    $showTerms = (bool) ($visibility['show_terms'] ?? true);
    $showNotes = (bool) ($visibility['show_notes'] ?? true);
    $showBankDetails = (bool) ($visibility['show_bank_details'] ?? false);
    $showSignature = (bool) ($visibility['show_signature'] ?? false);

    $documentMode = (string) ($mode ?? 'preview');
    $isEmbedded = $documentMode === 'embed';
    $isPdf = $documentMode === 'pdf';

    $field = fn (string $key, $fallback = null) => is_array($invoice)
        ? ($invoice[$key] ?? $fallback)
        : ($invoice->{$key} ?? $fallback);

    $showPrices = $type->showsPrices();
    $secondDateLabel = $type->secondDateLabel();
    $secondDateField = $type->secondDateField();
    $issueDate = $field('issue_date');
    $secondDate = $secondDateField === null ? null : $field($secondDateField);
    $number = $field('number') ?: '—';
    $subtitle = trim((string) ($field('title') ?: $field('reference') ?: ''));

    $currencyCode = $currency->code ?? $field('currency_code', 'ZMW');
    $symbol = trim((string) ($currency->symbol ?? ''));
    $precision = (int) ($currency->precision ?? 2);
    $money = fn ($n) => ($symbol !== '' ? $symbol : $currencyCode).' '.number_format(is_numeric($n) ? (float) $n : 0, $precision, '.', ',');
    $plain = fn ($n, int $places) => number_format((float) $n, $places, '.', ',');

    $date = function ($value) {
        if (blank($value)) {
            return null;
        }

        $parsed = $value instanceof \DateTimeInterface ? $value : rescue(fn () => \Illuminate\Support\Carbon::parse($value), null, false);

        return $parsed ? $parsed->format('d F Y') : (string) $value;
    };

    $rows = collect($items ?? $field('items', []))->map(function ($r) {
        $get = fn (array $keys, $fallback = null) => collect($keys)
            ->map(fn ($k) => is_array($r) ? ($r[$k] ?? null) : ($r->{$k} ?? null))
            ->first(fn ($v) => $v !== null, $fallback);

        $qty = (float) $get(['quantity', 'qty'], 0);
        $price = (float) $get(['unit_price', 'price'], 0);

        return [
            'desc' => (string) $get(['description', 'desc'], ''),
            'qty' => $qty,
            'price' => $price,
            'total' => (float) $get(['line_total'], $qty * $price),
        ];
    })->values();

    $storedSubtotal = $field('subtotal');
    $storedDiscount = $field('discount_total');
    $storedTax = $field('tax_total');
    $storedTotal = $field('total');
    $taxPercent = $field('tax_percent');

    $subtotal = is_numeric($storedSubtotal) ? (float) $storedSubtotal : (float) $rows->sum('total');
    $discount = is_numeric($storedDiscount) ? (float) $storedDiscount : 0.0;
    $tax = is_numeric($storedTax) ? (float) $storedTax : 0.0;
    $grandTotal = is_numeric($storedTotal) ? (float) $storedTotal : ($subtotal - $discount + $tax);
    $taxLabel = is_numeric($taxPercent) && (float) $taxPercent > 0
        ? 'VAT ('.rtrim(rtrim(number_format((float) $taxPercent, 2, '.', ''), '0'), '.').'%)'
        : 'VAT';

    $termsText = trim(strip_tags((string) ($template->terms_html ?? $field('terms', ''))));
    $notesText = trim((string) $field('notes', ''));
    $footerHtml = $template->footer_html ?? '';
    $bankHtml = $template->bank_html ?? ($company->bank_details_html ?? $company->bank_details ?? null);
    $signName = $company->signatory_name ?? 'Authorised signatory';

    $logoSrc = \App\Support\DocumentLogo::src($company->logo_path ?? null, $isPdf);

    $qrUrl = trim((string) ($content['qr_url'] ?? ''));
    $qrImage = $showQr ? \App\Support\QrCodeSvg::dataUri($qrUrl, $panel) : null;
    $tagline = trim((string) ($content['tagline'] ?? ''));

    $companyName = $company->name ?? 'Company';
    $initials = collect(preg_split('/\s+/', trim($companyName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

    $fontFamily = match ($fontKey) {
        'Roboto' => "Roboto, Helvetica, Arial, sans-serif",
        'Arial' => "Arial, Helvetica, sans-serif",
        default => "Inter, Helvetica, Arial, sans-serif",
    };

    /**
     * DomPDF only carries regular and bold faces; a numeric weight it has no
     * face for drops the text to its serif default.
     */
    $semibold = $isPdf ? 'bold' : '600';
    $bold = $isPdf ? 'bold' : '700';
    $heavy = $isPdf ? 'bold' : '800';

    $gutter = match ($density) {
        'compact' => '10mm',
        'airy' => '15mm',
        default => '12mm',
    };
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $type->documentTitle() }} {{ $field('number', '') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @unless($isPdf)
        @if($fontKey === 'Inter')
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        @elseif($fontKey === 'Roboto')
            <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
        @endif
    @endunless
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: {!! $fontFamily !!}; color: {{ $ink }}; font-size: 12px; line-height: 1.45; background: {{ $isPdf ? '#F1F3F6' : '#E5E7EB' }}; }

        .page-wrap { padding: 24px 0; }
        .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; background: #F1F3F6; padding: 11mm {{ $gutter }} 26mm; box-shadow: 0 18px 60px rgba(0,0,0,.12); }
        @if($isPdf || $isEmbedded)
            .page-wrap { padding: 0; }
            .sheet { box-shadow: none; }
        @endif

        table { border-collapse: collapse; }
        .muted { color: #6B7280; }

        .top { width: 100%; }
        .top td { vertical-align: top; padding: 0; }
        /*
         * The QR tile straddles the top of the panel: 20mm above it, 12mm inside.
         * It is pinned over one panel block rather than split across table cells,
         * because DomPDF stretched a rowspan past the tile and left hairline seams
         * wherever two fills met.
         */
        .side { position: relative; }
        .side-lead { height: 20mm; }
        .panel { background: {{ $panel }}; color: #FFFFFF; padding: 25mm 9mm 9mm; min-height: 76mm; border-bottom-left-radius: 6mm; }
        .tile { position: absolute; top: 0; left: 18mm; width: 32mm; height: 32mm; background: #FFFFFF; border-radius: 3mm; text-align: center; padding: 3.5mm; }
        .tile img { width: 25mm; height: 25mm; }
        .tile .initials { line-height: 25mm; font-size: 30px; font-weight: {{ $heavy }}; color: {{ $panel }}; }
        .panel .label { font-weight: {{ $bold }}; font-size: 12px; }
        .panel .value { opacity: .85; margin-bottom: 4mm; }
        .panel .rule { width: 7mm; height: 1px; background: #FFFFFF; opacity: .6; margin: 1mm 0 5mm; }

        .brand td { vertical-align: middle; }
        .brand img { max-height: 11mm; max-width: 40mm; }
        .brand .name { font-size: 18px; font-weight: {{ $bold }}; line-height: 1.15; }
        .brand .tagline { font-size: 11px; color: {{ $accent }}; font-weight: {{ $semibold }}; }
        .title { font-size: 42px; font-weight: {{ $heavy }}; letter-spacing: -1px; line-height: 1; margin-top: 12mm; text-transform: uppercase; }
        .title .dot { color: {{ $accent }}; }
        .subtitle { color: #6B7280; margin-top: 2.5mm; font-size: 13px; }
        .card { width: 100%; background: #FFFFFF; border-radius: 2mm; margin-top: 8mm; }
        .card td { width: 50%; text-align: center; padding: 5mm 2mm; }
        .card td + td { border-left: 1px solid #E5E7EB; }
        .card .k { display: block; color: #6B7280; font-size: 11px; }
        .card .v { font-size: 14px; font-weight: {{ $bold }}; }
        .from { margin-top: 8mm; width: 100%; }
        .from td { vertical-align: top; }
        .from .head { width: 27mm; font-weight: {{ $bold }}; font-size: 13px; }
        .from .bar { width: 6mm; height: 1.5px; background: {{ $accent }}; margin-top: 2mm; }

        .items-card { background: #FFFFFF; border-radius: 2mm; margin-top: 11mm; }
        .items { width: 100%; }
        .items th { background: {{ $panel }}; color: #FFFFFF; text-align: left; font-weight: {{ $semibold }}; font-size: 12px; padding: 5mm 3mm; }
        .items th:first-child { border-top-left-radius: 2mm; padding-left: 10mm; }
        .items th:last-child { border-top-right-radius: 2mm; padding-right: 10mm; }
        .items td { padding: 4.5mm 3mm; vertical-align: top; }
        .items td:first-child { padding-left: 10mm; }
        .items td:last-child { padding-right: 10mm; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .items .strong { font-weight: {{ $semibold }}; }
        .items.striped tbody tr:nth-child(even) td { background: #F8F9FB; }
        .items.lined tbody tr + tr td { border-top: 1px solid #EEF0F4; }

        .sums { width: 100%; border-top: 1px solid #E5E7EB; }
        .sums td { padding: 1.4mm 0; }
        .sums .pad { width: 50%; }
        .sums .k { font-weight: {{ $semibold }}; }
        .sums .v { text-align: right; padding-right: 10mm; }
        .sums .first td { padding-top: 6mm; }
        .sums .total td { font-size: 17px; font-weight: {{ $heavy }}; color: {{ $panel }}; padding-top: 3mm; padding-bottom: 7mm; border-top: 1px solid #E5E7EB; }

        .foot { width: 100%; margin-top: 9mm; }
        .foot > tbody > tr > td { vertical-align: top; width: 50%; padding: 0 10mm; }
        .foot h4 { margin: 0 0 1.5mm; font-size: 12px; color: {{ $ink }}; }
        .foot .block { color: #6B7280; line-height: 1.65; margin-bottom: 5mm; white-space: pre-line; }
        .contact { margin-bottom: 4mm; }
        .contact td { vertical-align: middle; }
        .contact .icon { width: 7.5mm; height: 7.5mm; background: {{ $panel }}; border-radius: 1.5mm; text-align: center; }
        .contact .icon img { width: 4mm; height: 4mm; margin-top: 1.75mm; }
        .contact .text { padding-left: 3.5mm; color: #6B7280; line-height: 1.4; }
        .contact .text b { color: {{ $ink }}; font-weight: {{ $semibold }}; }

        .signatures { width: 100%; margin-top: 10mm; }
        .signatures td { width: 50%; padding: 0 10mm; vertical-align: top; }
        .signatures .line { border-top: 1px solid #C3CBD8; padding-top: 2.5mm; font-size: 11px; color: #6B7280; }
        .signatures b { color: {{ $ink }}; font-size: 12px; }

        .company-footer { margin: 8mm 10mm 0; text-align: center; font-size: 11px; color: #6B7280; }
        .band { position: absolute; left: 0; right: 0; bottom: 0; height: 2.2mm; background: {{ $panel }}; }
        .band span { display: block; float: right; width: 22%; height: 2.2mm; background: {{ $accent }}; }

        .screen-toolbar { position: sticky; top: 0; z-index: 20; padding: 16px 24px 0; }
        .screen-toolbar-inner { width: 210mm; max-width: 100%; margin: 0 auto; background: rgba(17,24,39,.92); color: #FFFFFF; border-radius: 14px; padding: 12px 14px; font-size: 12px; overflow: hidden; }
        .screen-toolbar-inner button { float: right; margin-left: 8px; border: 0; border-radius: 10px; padding: 8px 14px; font: inherit; font-weight: {{ $bold }}; cursor: pointer; background: {{ $accent }}; color: #FFFFFF; }
        .screen-toolbar-inner button.secondary { background: rgba(255,255,255,.12); }
        .screen-toolbar-inner span { line-height: 32px; opacity: .88; }

        @media print {
            body { background: #F1F3F6; }
            .screen-toolbar { display: none !important; }
            .page-wrap { padding: 0; }
            .sheet { box-shadow: none; }
        }

        @if($isPdf)
            {{-- Last in the sheet so it wins the cascade. DomPDF ignores box-sizing on blocks and has no Inter; spacing is tighter so a typical document stays on one page. --}}
            body, .sheet { font-family: Helvetica, Arial, sans-serif; }
            .sheet { width: auto; min-height: 0; padding-top: 6mm; padding-bottom: 4mm; }
            .panel { min-height: 0; padding: 20mm 9mm 4mm; }
            .tile { width: 25mm; height: 25mm; }
            .title { margin-top: 9mm; }
            .card, .from { margin-top: 6mm; }
            .items-card { position: relative; margin-top: 6mm; }
            {{-- Abutting cell fills leave hairlines in PDF viewers; one bar of the same colour behind the header row hides them. --}}
            .items-head { position: absolute; z-index: -1; top: 0; left: 0; right: 0; height: 13mm; background: {{ $panel }}; }
            .items th { height: 5mm; padding-top: 4mm; padding-bottom: 4mm; }
            .items th:first-child, .items th:last-child { border-radius: 0; }
            .items td { padding-top: 2.5mm; padding-bottom: 2.5mm; }
            .sums .first td { padding-top: 3mm; }
            .sums .total td { padding-bottom: 3mm; }
            .foot { margin-top: 4mm; }
            .foot .block { margin-bottom: 3mm; line-height: 1.5; }
            .contact { margin-bottom: 1.5mm; }
            .signatures { margin-top: 4mm; }
            .band { position: fixed; }
        @endif
    </style>
</head>
<body>
@if(! in_array($documentMode, ['pdf', 'embed'], true))
    <div class="screen-toolbar">
        <div class="screen-toolbar-inner">
            <button type="button" class="secondary" onclick="openPrintDialog()">Print</button>
            <button type="button" onclick="downloadPdf()">Download PDF</button>
            <span>{{ $documentMode === 'print' ? 'Print-ready' : 'Preview-ready' }} {{ $type->label() }}. Use Print, or choose "Save as PDF" to download it.</span>
        </div>
    </div>
@endif

<div class="page-wrap">
    <div class="sheet meridian">
        <table class="top">
            <tr>
                <td style="width: 72mm;">
                    <div class="side">
                        <div class="side-lead"></div>
                        <div class="panel">

                            <div class="label">Date</div>
                            <div class="value">{{ $date($issueDate) ?? '—' }}</div>
                            @if($secondDateLabel !== null)
                                <div class="label">{{ $secondDateLabel }}</div>
                                <div class="value">{{ $date($secondDate) ?? '—' }}</div>
                            @endif

                            <div class="rule"></div>

                            <div class="label">{{ rtrim($type->counterpartyLabel(), ':') }}</div>
                            <div class="value" style="margin-bottom: 1mm;">{{ $client->name ?? '—' }}</div>
                            @if($showContactPerson && ! empty($client->contact_person))
                                <div class="value" style="margin-bottom: 1mm;">Attn: {{ $client->contact_person }}</div>
                            @endif
                            @if(! empty($client->address))
                                <div class="value" style="margin-bottom: 1mm;">{{ $client->address }}</div>
                            @endif
                            <div style="height: 3mm;"></div>
                            @if(! empty($client->phone))
                                <div class="value" style="margin-bottom: 1mm;">{{ $client->phone }}</div>
                            @endif
                            @if($showClientEmail && ! empty($client->email))
                                <div class="value" style="margin-bottom: 1mm;">{{ $client->email }}</div>
                            @endif
                            @if(! empty($client->tpin))
                                <div class="value" style="margin-bottom: 1mm;">TPIN: {{ $client->tpin }}</div>
                            @endif
                        </div>
                        <div class="tile">
                            @if($qrImage)
                                <img src="{{ $qrImage }}" alt="QR code">
                            @elseif($showLogo && $logoSrc)
                                <img src="{{ $logoSrc }}" alt="Logo" style="object-fit: contain;">
                            @else
                                <div class="initials">{{ $initials }}</div>
                            @endif
                        </div>
                    </div>
                </td>
                <td style="width: 14mm;"></td>
                <td>
                    <table class="brand">
                        <tr>
                            @if($showLogo && $logoSrc && $qrImage)
                                <td style="padding-right: 3mm;"><img src="{{ $logoSrc }}" alt="Logo"></td>
                            @endif
                            <td>
                                <div class="name">{{ $companyName }}</div>
                                @if($tagline !== '')
                                    <div class="tagline">{{ $tagline }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>

                    <div class="title">{{ $type->documentTitle() }}<span class="dot">.</span></div>
                    @if($subtitle !== '')
                        <div class="subtitle">{{ $subtitle }}</div>
                    @endif

                    <table class="card">
                        <tr>
                            <td><span class="k">{{ ucfirst($type->label()) }} No.</span><span class="v">{{ $number }}</span></td>
                            @if($showPrices)
                                <td><span class="k">Amount</span><span class="v">{{ $money($grandTotal) }}</span></td>
                            @else
                                <td><span class="k">Items</span><span class="v">{{ $rows->count() }}</span></td>
                            @endif
                        </tr>
                    </table>

                    <table class="from">
                        <tr>
                            <td class="head">From<div class="bar"></div></td>
                            <td class="muted">
                                {{ $companyName }}
                                @if(! empty($company->address))<br>{{ $company->address }}@endif
                                @if(! empty($company->tpin))<br>TPIN: {{ $company->tpin }}@endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="items-card">
            @if($isPdf)
                <div class="items-head"></div>
            @endif
            <table class="items {{ in_array($tableStyle, ['striped', 'lined'], true) ? $tableStyle : '' }}">
                <thead>
                    <tr>
                        <th>Item description</th>
                        <th class="center" style="width: 18mm;">Qty</th>
                        @if($showPrices)
                            <th class="num" style="width: 32mm;">Unit price</th>
                            <th class="num" style="width: 36mm;">Amount</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['desc'] ?: '—' }}</td>
                            <td class="center">{{ $plain($row['qty'], fmod($row['qty'], 1.0) === 0.0 ? 0 : 2) }}</td>
                            @if($showPrices)
                                <td class="num">{{ $money($row['price']) }}</td>
                                <td class="num strong">{{ $money($row['total']) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showPrices ? 4 : 2 }}" class="muted">No items.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($showPrices)
                <table class="sums">
                    <tr class="first"><td class="pad"></td><td class="k">Subtotal</td><td class="v">{{ $money($subtotal) }}</td></tr>
                    @if($discount > 0)
                        <tr><td class="pad"></td><td class="k">Discount</td><td class="v">- {{ $money($discount) }}</td></tr>
                    @endif
                    @if($tax > 0)
                        <tr><td class="pad"></td><td class="k">{{ $taxLabel }}</td><td class="v">{{ $money($tax) }}</td></tr>
                    @endif
                    <tr class="total"><td class="pad" style="border-top: 0;"></td><td>Total</td><td class="v">{{ $money($grandTotal) }}</td></tr>
                </table>
            @else
                <div style="height: 4mm;"></div>
            @endif
        </div>

        <table class="foot">
            <tr>
                <td>
                    @if($showNotes && $notesText !== '')
                        <h4>Notes</h4>
                        <div class="block">{{ $notesText }}</div>
                    @endif
                    @if($showTerms && $termsText !== '')
                        <h4>Terms</h4>
                        <div class="block">{{ $termsText }}</div>
                    @endif
                    @if($showBankDetails && ! empty($bankHtml))
                        <h4>Payment details</h4>
                        <div class="block" style="white-space: normal;">{!! $bankHtml !!}</div>
                    @endif
                    @if(! ($showNotes && $notesText !== '') && ! ($showTerms && $termsText !== ''))
                        <h4>{{ $type->closingTitle() }}</h4>
                        <div class="block">{{ $type->closingLine() }}</div>
                    @endif
                </td>
                <td>
                    @php
                        $icon = fn (string $paths) => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>');
                        $contacts = array_filter([
                            ['Email', $company->email ?? null, '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>'],
                            ['Phone', $company->phone ?? null, '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>'],
                            ['Website', $qrUrl !== '' ? preg_replace('#^https?://(www\.)?#', '', rtrim($qrUrl, '/')) : null, '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>'],
                        ], fn ($c) => filled($c[1]));
                    @endphp
                    @foreach($contacts as [$label, $value, $paths])
                        <table class="contact">
                            <tr>
                                <td class="icon"><img src="{{ $icon($paths) }}" alt=""></td>
                                <td class="text"><b>{{ $label }}</b><br>{{ $value }}</td>
                            </tr>
                        </table>
                    @endforeach
                </td>
            </tr>
        </table>

        @if($type === \App\Enums\DocumentType::DeliveryNote)
            <table class="signatures">
                <tr>
                    <td><div class="line"><b>{{ $invoice->received_by ?? 'Received by' }}</b><br>Name &amp; signature</div></td>
                    <td><div class="line"><b>{{ $date($invoice->received_on ?? null) ?? 'Date' }}</b><br>Date received</div></td>
                </tr>
            </table>
        @elseif($showSignature)
            <table class="signatures">
                <tr>
                    <td><div class="line"><b>{{ $companyName }}</b><br>{{ $signName }}</div></td>
                    <td><div class="line"><b>Accepted by</b><br>Name, signature &amp; date</div></td>
                </tr>
            </table>
        @endif

        @if(! empty($footerHtml))
            <div class="company-footer">{!! $footerHtml !!}</div>
        @endif

        <div class="band"><span></span></div>
    </div>
</div>

<script>
    window.openPrintDialog = () => { window.focus(); window.print(); };
    window.downloadPdf = () => window.openPrintDialog();
    @if(($autoPrint ?? false))
        window.addEventListener('load', () => window.setTimeout(() => window.downloadPdf(), 150), { once: true });
    @endif
</script>
</body>
</html>
