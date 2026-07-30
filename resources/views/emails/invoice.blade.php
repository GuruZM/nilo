<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $documentLabel }}@if($companyName) from {{ $companyName }}@endif</title>
    <style>
        @media (prefers-color-scheme: dark) {
            body, .email-body { background-color: #001222 !important; }
            .email-card { background-color: #0a2137 !important; border-left-color: #16334f !important; border-right-color: #16334f !important; }
            .email-heading { color: #ffffff !important; }
            .email-text { color: #e6edf5 !important; }
            .email-muted { color: #ffffff !important; }
            .email-tile { background-color: #06192b !important; }
        }
    </style>
</head>
<body class="email-body" style="margin:0;padding:0;background-color:#eef5fb;font-family:'Archivo','Helvetica Neue',Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-body" style="background-color:#eef5fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
                    @include('emails.partials.company-header')
                    <tr>
                        <td class="email-card" style="background-color:#ffffff;padding:40px;border-left:1px solid #d8e7f5;border-right:1px solid #d8e7f5;">
                            <h1 class="email-heading" style="margin:0 0 16px;color:#001d3a;font-size:24px;font-weight:700;">
                                {{ $documentLabel }}@if($companyName) from {{ $companyName }}@endif
                            </h1>

                            <p class="email-text" style="margin:0 0 12px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                Hi {{ $recipientName }},
                            </p>

                            <p class="email-text" style="margin:0 0 24px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                Please find {{ $documentLabel }} attached as a PDF. The key details are below.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="email-tile" style="margin:0 0 28px;background-color:#f4f8fc;border-radius:12px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="padding:0 0 10px;color:#8494a7;font-size:13px;">{{ $amountLabel ?? 'Amount due' }}</td>
                                                <td align="right" style="padding:0 0 10px;color:#001d3a;font-size:18px;font-weight:700;">
                                                    {{ $totalFormatted }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 10px;color:#8494a7;font-size:13px;">Issued</td>
                                                <td align="right" style="padding:0 0 10px;color:#3d4a5c;font-size:14px;">
                                                    {{ $issueDate }}
                                                </td>
                                            </tr>
                                            @if($dueDate)
                                                <tr>
                                                    <td style="padding:0;color:#8494a7;font-size:13px;">{{ $secondDateLabel ?? 'Due' }}</td>
                                                    <td align="right" style="padding:0;color:#3d4a5c;font-size:14px;">
                                                        {{ $dueDate }}
                                                    </td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            @if($notes)
                                <p class="email-text" style="margin:0 0 24px;color:#3d4a5c;font-size:15px;line-height:1.6;white-space:pre-wrap;">{{ $notes }}</p>
                            @endif

                            <p class="email-muted" style="margin:0;color:#8494a7;font-size:13px;line-height:1.6;">
                                @if($companyName)
                                    Questions about this {{ $documentNoun ?? 'invoice' }}? Reply to this email and it will reach {{ $companyName }} directly.
                                @else
                                    Questions about this {{ $documentNoun ?? 'invoice' }}? Just reply to this email.
                                @endif
                            </p>
                        </td>
                    </tr>
                    @include('emails.partials.company-footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
