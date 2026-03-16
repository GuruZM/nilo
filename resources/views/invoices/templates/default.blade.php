{{-- resources/views/invoices/templates/default.blade.php --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ data_get($invoice, 'number', '') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    @php
        /**
         * ✅ Normal HTML (browser-rendered), designed to MATCH the Builder "Wave Premium" look.
         * Later you can convert this HTML to PDF using Browsershot/Chrome (best) without changing markup.
         *
         * Controller passes:
         * - $company, $client, $invoice, $items, $currency, $template, $settings
         */

        $s = is_array($settings ?? null) ? $settings : [];

        // --------- Builder settings (current shape)
        $brand = (array)($s['brand'] ?? []);
        $layout = (array)($s['layout'] ?? []);
        $visibility = (array)($s['visibility'] ?? []);

        // Fallbacks (if older settings exist)
        $primary = (string)($brand['primary'] ?? data_get($s, 'brand.primary_color') ?? ($company->primary_color ?? '#111827'));
        $accent  = (string)($brand['accent']  ?? data_get($s, 'brand.accent_color')  ?? '#F59E0B');
        $fontKey = (string)($brand['font']    ?? data_get($s, 'typography.font')     ?? 'Inter');

        $headerLayout = (string)($layout['header'] ?? 'split'); // split | left | center
        $tableStyle   = (string)($layout['table'] ?? 'striped'); // striped | lined | clean
        $density      = (string)($layout['density'] ?? 'normal'); // compact | normal | airy

        $showLogo          = (bool)($visibility['show_logo'] ?? true);
        $showClientEmail   = (bool)($visibility['show_client_email'] ?? true);
        $showContactPerson = (bool)($visibility['show_contact_person'] ?? true);
        $showTerms         = (bool)($visibility['show_terms'] ?? true);
        $showNotes         = (bool)($visibility['show_notes'] ?? true);
        $documentMode      = (string)($mode ?? 'preview');

        // ✅ now used by blade too
        $showBankDetails   = (bool)($visibility['show_bank_details'] ?? false);
        $showSignature     = (bool)($visibility['show_signature'] ?? false);

        // --------- Invoice data
        $isArray = is_array($invoice);

        $invoiceNumber = $isArray ? ($invoice['number'] ?? '—') : ($invoice->number ?? '—');

        $issueDate = $isArray ? ($invoice['issue_date'] ?? null) : ($invoice->issue_date ?? null);
        $dueDate   = $isArray ? ($invoice['due_date'] ?? null) : ($invoice->due_date ?? null);

        $currencyCode = $currency->code ?? ($isArray ? ($invoice['currency_code'] ?? 'ZMW') : ($invoice->currency_code ?? 'ZMW'));
        $symbol       = $currency->symbol ?? '';
        $precision    = (int)($currency->precision ?? 2);

        $money = function ($n) use ($currencyCode, $symbol, $precision) {
            $val = is_numeric($n) ? (float)$n : 0;
            $fmt = number_format($val, $precision, '.', ',');
            $prefix = trim((string)$symbol) !== '' ? $symbol : $currencyCode;
            return $prefix . ' ' . $fmt;
        };

        $rows = $items ?? ($isArray ? ($invoice['items'] ?? []) : ($invoice->items ?? []));

        $itemsComputed = collect($rows)->map(function ($r) {
            $isArr = is_array($r);
            $desc  = $isArr ? ($r['description'] ?? $r['desc'] ?? '') : ($r->description ?? '');
            $qty   = $isArr ? (float)($r['quantity'] ?? $r['qty'] ?? 0) : (float)($r->quantity ?? 0);
            $price = $isArr ? (float)($r['unit_price'] ?? $r['price'] ?? 0) : (float)($r->unit_price ?? 0);
            $total = $isArr
                ? (float)($r['line_total'] ?? ($qty * $price))
                : (float)($r->line_total ?? ($qty * $price));

            return [
                'desc' => (string)$desc,
                'qty' => $qty,
                'price' => $price,
                'total' => $total,
            ];
        })->values();

        $subtotal = $itemsComputed->sum('total');

        // If your invoice model already stores totals, prefer them:
        $storedSubtotal = $isArray ? ($invoice['subtotal'] ?? null) : ($invoice->subtotal ?? null);
        $storedTax      = $isArray ? ($invoice['tax_total'] ?? null) : ($invoice->tax_total ?? null);
        $storedTotal    = $isArray ? ($invoice['total'] ?? null) : ($invoice->total ?? null);

        $subtotalFinal = is_numeric($storedSubtotal) ? (float)$storedSubtotal : (float)$subtotal;
        $vatFinal      = is_numeric($storedTax) ? (float)$storedTax : 0.0;
        $grandFinal    = is_numeric($storedTotal) ? (float)$storedTotal : ($subtotalFinal + $vatFinal);

        // Terms/footer
        $termsText = $template->terms_html ?? ($isArray ? ($invoice['terms'] ?? '') : ($invoice->terms ?? ''));
        $footerHtml = $template->footer_html ?? '';

        $notesText = $isArray ? ($invoice['notes'] ?? '') : ($invoice->notes ?? '');

        // --------- Company logo (browser safe)
        $companyLogoUrl = !empty($company?->logo_path) ? asset('storage/' . ltrim($company->logo_path, '/')) : null;

        // --------- Font mapping
        $fontFamily = match ($fontKey) {
            'Roboto' => 'Roboto, system-ui, -apple-system, Segoe UI, Arial, sans-serif',
            'Arial'  => 'Arial, system-ui, -apple-system, Segoe UI, sans-serif',
            default  => 'Inter, system-ui, -apple-system, Segoe UI, Arial, sans-serif',
        };

        // --------- Density padding
        $padX = match ($density) {
            'compact' => '28px',
            'airy' => '40px',
            default => '32px',
        };
        $padY = match ($density) {
            'compact' => '28px',
            'airy' => '40px',
            default => '32px',
        };

        // --------- Helpers
        $hexToRgba = function (string $hex, float $alpha) {
            $h = ltrim(trim($hex), '#');
            if (strlen($h) === 3) {
                $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
            }
            if (strlen($h) !== 6) return "rgba(0,0,0,{$alpha})";
            $r = hexdec(substr($h, 0, 2));
            $g = hexdec(substr($h, 2, 2));
            $b = hexdec(substr($h, 4, 2));
            return "rgba({$r},{$g},{$b},{$alpha})";
        };

        $accentSoft = $hexToRgba($accent, 0.14);

        // --------- Bank/signature (fallbacks)
        // You can later add these into template settings/content and store them in DB.
        $bankHtml = $template->bank_html
            ?? ($company->bank_details_html ?? $company->bank_details ?? null);

        $signName  = $company->signatory_name ?? 'Authorized Signatory';
        $signTitle = $company->signatory_title ?? '';
    @endphp

    {{-- Fonts (browser) --}}
    @if($fontKey === 'Inter')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    @elseif($fontKey === 'Roboto')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    @endif

    <style>
        :root{
            --primary: {{ $primary }};
            --accent: {{ $accent }};
            --accentSoft: {{ $accentSoft }};
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #ffffff;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            font-family: {!! json_encode($fontFamily) !!};
            color: #111827;
        }

        .screen-toolbar{
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: center;
            padding: 16px 24px 0;
        }

        .screen-toolbar-inner{
            width: min(210mm, 100%);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            background: rgba(17, 24, 39, 0.92);
            color: #ffffff;
            border-radius: 14px;
            padding: 12px 14px;
            box-shadow: 0 12px 34px rgba(0,0,0,0.18);
        }

        .screen-toolbar-copy{
            font-size: 12px;
            line-height: 1.45;
            opacity: 0.88;
        }

        .screen-toolbar-actions{
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .toolbar-button{
            appearance: none;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 120ms ease, opacity 120ms ease;
        }

        .toolbar-button:hover{
            transform: translateY(-1px);
        }

        .toolbar-button-primary{
            background: var(--accent);
            color: var(--primary);
        }

        .toolbar-button-secondary{
            background: rgba(255,255,255,0.12);
            color: #ffffff;
        }

        .page-wrap{
            padding: 24px;
            display: flex;
            justify-content: center;
        }

        /* True A4 canvas for browser preview */
        .sheet{
            width: 210mm;
            min-height: 297mm;
            max-width: 100%;
            background: var(--bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 18px 60px rgba(0,0,0,0.12);
        }

        /* HERO */
        .hero{
            position: relative;
            background: var(--primary);
            color: #fff;
        }
        .hero-inner{
            padding: {{ $padY }} {{ $padX }};
            padding-bottom: 72px;
            position: relative;
            z-index: 2;
        }
        .hero-row{
            display: flex;
            gap: 18px;
            align-items: flex-start;
            justify-content: space-between;
        }
        .hero-center .hero-row{
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .logo{
            width: 48px;
            height: 48px;
            border-radius: 12px;
            overflow: hidden;
            display: grid;
            place-items: center;
            background: var(--accent);
            color: var(--primary);
            font-weight: 800;
            font-size: 11px;
        }
        .logo img{
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .company-name{
            font-weight: 700;
            font-size: 18px;
            line-height: 1.2;
        }
        .company-meta{
            font-size: 12px;
            opacity: .82;
            margin-top: 6px;
            line-height: 1.5;
        }

        .doc-title{
            font-weight: 800;
            font-size: 32px;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .doc-meta{
            margin-top: 12px;
            font-size: 12px;
            opacity: .82;
            line-height: 1.55;
        }

        .wave{
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 140px;
            z-index: 1;
        }

        /* BODY */
        .body{
            padding: {{ $padY }} {{ $padX }};
            padding-top: 22px;
        }

        .row-top{
            display: flex;
            gap: 18px;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 26px;
        }
        .billto{
            min-width: 0;
        }
        .label-accent{
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--accent);
        }
        .client-name{
            margin-top: 6px;
            font-size: 14px;
            font-weight: 700;
        }
        .client-meta{
            margin-top: 2px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        .summary{
            width: 280px;
            max-width: 100%;
            background: var(--accentSoft);
            border-radius: 14px;
            padding: 14px 16px;
        }
        .summary-row{
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--muted);
            margin-top: 8px;
        }
        .summary-row strong{
            color: #111827;
            font-weight: 700;
        }
        .grand{
            margin-top: 12px;
            background: var(--accent);
            color: var(--primary);
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 13px;
        }

        /* TABLE */
        .table{
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }
        .thead{
            display: grid;
            grid-template-columns: 6fr 2fr 2fr 2fr;
            gap: 10px;
            padding: 12px 16px;
            background: var(--accent);
            color: var(--primary);
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .trow{
            display: grid;
            grid-template-columns: 6fr 2fr 2fr 2fr;
            gap: 10px;
            padding: 14px 16px;
            font-size: 13px;
            align-items: start;
        }
        .trow + .trow{
            border-top: 1px solid rgba(0,0,0,.06);
        }
        .striped .trow:nth-child(even){
            background: rgba(0,0,0,0.03);
        }
        .lined .trow{
            background: #fff;
        }
        .clean .trow + .trow{
            border-top: 1px solid rgba(0,0,0,.05);
        }

        .desc{
            font-weight: 700;
            margin: 0;
        }
        .desc-sub{
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }
        .num{
            text-align: right;
            white-space: nowrap;
        }

        /* BOTTOM BLOCKS */
        .bottom{
            margin-top: 28px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 720px){
            .row-top{ flex-direction: column; }
            .summary{ width: 100%; }
            .bottom{ grid-template-columns: 1fr; }
        }

        .card{
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
            background: #fff;
        }
        .card-title{
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: .06em;
        }
        .card-body{
            margin-top: 10px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.55;
            white-space: pre-wrap;
        }

        .footer{
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid rgba(0,0,0,.06);
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        .right-align { text-align: right; }

        @page{
            size: A4;
            margin: 0;
        }

        @media print{
            body{
                background: #ffffff;
            }

            .screen-toolbar{
                display: none !important;
            }

            .page-wrap{
                padding: 0;
            }

            .sheet{
                width: 100%;
                min-height: auto;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
<div class="screen-toolbar">
    <div class="screen-toolbar-inner">
        <div class="screen-toolbar-copy">
            {{ $documentMode === 'print' ? 'Print-ready invoice' : 'Preview-ready invoice' }}.
            Use Print to send to a printer or choose "Save as PDF" to download it.
        </div>

        <div class="screen-toolbar-actions">
            <button type="button" class="toolbar-button toolbar-button-primary" onclick="downloadPdf()">
                Download PDF
            </button>
            <button type="button" class="toolbar-button toolbar-button-secondary" onclick="openPrintDialog()">
                Print
            </button>
        </div>
    </div>
</div>

<div class="page-wrap">
    <div class="sheet">

        {{-- =================== HERO HEADER =================== --}}
        <div class="hero {{ $headerLayout === 'center' ? 'hero-center' : '' }}">
            <div class="hero-inner">
                <div class="hero-row">
                    <div style="display:flex; gap:14px; align-items:center;">
                        @if($showLogo)
                            <div class="logo">
                                @if(!empty($companyLogoUrl))
                                    <img src="{{ $companyLogoUrl }}" alt="Logo">
                                @else
                                    LOGO
                                @endif
                            </div>
                        @endif

                        <div style="min-width:0;">
                            <div class="company-name">{{ $company->name ?? 'Company' }}</div>
                            <div class="company-meta">
                                <div>{{ $company->address ?? '' }}</div>
                                <div>
                                    @if(!empty($company->phone)){{ $company->phone }}@endif
                                    @if(!empty($company->phone) && !empty($company->email)) • @endif
                                    @if(!empty($company->email)){{ $company->email }}@endif
                                </div>
                                @if(!empty($company->tpin))
                                    <div>TPIN: {{ $company->tpin }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="{{ $headerLayout === 'center' ? '' : 'right-align' }}">
                        <div class="doc-title">INVOICE</div>
                        <div class="doc-meta">
                            <div>Invoice No: {{ $invoiceNumber }}</div>
                            <div>Date: {{ $issueDate ?? '—' }}</div>
                            <div>Due: {{ $dueDate ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- wave --}}
            <svg class="wave" viewBox="0 0 1440 140" preserveAspectRatio="none">
                <path fill="#ffffff"
                      d="M0,96L60,90.7C120,85,240,75,360,74.7C480,75,600,85,720,96C840,107,960,117,1080,117.3C1200,117,1320,107,1380,101.3L1440,96L1440,140L1380,140C1320,140,1200,140,1080,140C960,140,840,140,720,140C600,140,480,140,360,140C240,140,120,140,60,140L0,140Z" />
            </svg>
        </div>

        {{-- =================== BODY =================== --}}
        <div class="body">

            {{-- Bill To + Summary --}}
            <div class="row-top">
                <div class="billto">
                    <div class="label-accent">To:</div>
                    <div class="client-name">{{ $client->name ?? '—' }}</div>
                    @if(!empty($client->address))
                        <div class="client-meta">{{ $client->address }}</div>
                    @endif

                    @if($showClientEmail && !empty($client->email))
                        <div class="client-meta">{{ $client->email }}</div>
                    @endif

                    @if($showContactPerson && !empty($client->contact_person))
                        <div class="client-meta">Attn: {{ $client->contact_person }}</div>
                    @endif
                </div>

                <div class="summary">
                    <div class="summary-row" style="margin-top:0;">
                        <span>Sub Total</span>
                        <strong>{{ $money($subtotalFinal) }}</strong>
                    </div>

                    <div class="summary-row">
                        <span>VAT</span>
                        <strong>{{ $money($vatFinal) }}</strong>
                    </div>

                    <div class="grand">
                        <span>GRAND TOTAL</span>
                        <span>{{ $money($grandFinal) }}</span>
                    </div>
                </div>
            </div>

            {{-- Items table --}}
            <div class="table {{ $tableStyle === 'striped' ? 'striped' : ($tableStyle === 'lined' ? 'lined' : 'clean') }}">
                <div class="thead">
                    <div>Item Description</div>
                    <div class="num">Price</div>
                    <div class="num">Qty</div>
                    <div class="num">Total</div>
                </div>

                @foreach($itemsComputed as $it)
                    <div class="trow">
                        <div>
                            <div class="desc">{{ $it['desc'] ?: '—' }}</div>
                            <div class="desc-sub">Contrary to popular belief Lorem ipsum simply random.</div>
                        </div>
                        <div class="num">{{ number_format((float)$it['price'], $precision, '.', ',') }}</div>
                        <div class="num">{{ number_format((float)$it['qty'], 2, '.', ',') }}</div>
                        <div class="num" style="font-weight:800;">{{ number_format((float)$it['total'], $precision, '.', ',') }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Bottom blocks --}}
            <div class="bottom">
                <div style="display:flex; flex-direction:column; gap:14px;">
                    {{-- Bank Details (was notes in the preview) --}}
                    @if($showBankDetails)
                        <div class="card">
                            <div class="card-title">Payment Info</div>

                            @if(!empty($bankHtml))
                                <div class="card-body" style="white-space: normal;">
                                    {!! $bankHtml !!}
                                </div>
                            @else
                                <div class="card-body">
                                    Bank: —<br>
                                    Account Name: —<br>
                                    Account No: —<br>
                                    Branch: —
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Terms --}}
                    @if($showTerms && !empty($termsText))
                        <div class="card">
                            <div class="card-title">Terms</div>
                            <div class="card-body">{{ strip_tags($termsText) }}</div>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-title">Thank you for your business!</div>

                    @if($showNotes && !empty($notesText))
                        <div class="card-body">{{ $notesText }}</div>
                    @else
                        <div class="card-body" style="white-space: normal;">
                            Thank you for your business. Kindly settle within due date.
                        </div>
                    @endif

                    {{-- Signature --}}
                    @if($showSignature)
                        <div style="margin-top: 18px; display:flex; justify-content:flex-end;">
                            <div style="text-align:right;">
                                <div style="font-size:14px; font-weight:800; color:#111827;">{{ $signName }}</div>
                                @if(!empty($signTitle))
                                    <div style="font-size:12px; color: var(--muted);">{{ $signTitle }}</div>
                                @endif
                                <div style="margin-top: 14px; font-size: 14px; font-weight:700; font-style: italic; opacity:.7;">
                                    Signature
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            @if(!empty($footerHtml))
                <div class="footer">
                    {!! $footerHtml !!}
                </div>
            @else
                <div class="footer">
                    Powered by {{ config('app.name') }}
                </div>
            @endif
        </div>
    </div>
</div>
<script>
    window.openPrintDialog = () => {
        window.focus();
        window.print();
    };

    window.downloadPdf = () => {
        window.openPrintDialog();
    };

    @if(($autoPrint ?? false))
        window.addEventListener('load', () => {
            window.setTimeout(() => {
                window.downloadPdf();
            }, 150);
        }, { once: true });
    @endif
</script>
</body>
</html>
