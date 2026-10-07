<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $heading }}</title>
    <style>
        @media (prefers-color-scheme: dark) {
            body, .email-body { background-color: #001222 !important; }
            .email-card { background-color: #0a2137 !important; border-left-color: #16334f !important; border-right-color: #16334f !important; }
            .email-heading { color: #ffffff !important; }
            .email-text { color: #e6edf5 !important; }
            .email-muted { color: #ffffff !important; }
        }
    </style>
</head>
<body class="email-body" style="margin:0;padding:0;background-color:#eef5fb;font-family:'Archivo','Helvetica Neue',Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-body" style="background-color:#eef5fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
                    @include('emails.partials.logo-header')
                    <tr>
                        <td class="email-card" style="background-color:#ffffff;padding:40px;border-left:1px solid #d8e7f5;border-right:1px solid #d8e7f5;">
                            <h1 class="email-heading" style="margin:0 0 16px;color:#001d3a;font-size:24px;font-weight:700;">
                                {{ $heading }}
                            </h1>
                            @foreach ($paragraphs as $paragraph)
                                <p class="email-text" style="margin:0 0 {{ $loop->last ? '28px' : '12px' }};color:#3d4a5c;font-size:15px;line-height:1.6;">
                                    {{ $paragraph }}
                                </p>
                            @endforeach
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                                <tr>
                                    <td style="border-radius:8px;background-color:#00417d;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;">
                                            {{ $actionLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p class="email-muted" style="margin:0;color:#8494a7;font-size:13px;line-height:1.6;">
                                Already paid by bank transfer or mobile money? It may still be waiting for us to confirm it. You can check on your <a href="{{ route('subscription.current') }}" style="color:#00417d;">billing page</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#001d3a;border-radius:0 0 16px 16px;padding:24px 40px;">
                            <p style="margin:0 0 4px;color:rgba(255,255,255,0.6);font-size:12px;">
                                &copy; {{ date('Y') }} Nilo. All rights reserved.
                            </p>
                            <p style="margin:0;color:rgba(255,255,255,0.4);font-size:12px;">
                                Crafted by Resonantt
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
