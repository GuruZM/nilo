<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Welcome to Nilo</title>
</head>
<body style="margin:0;padding:0;background-color:#eef5fb;font-family:'Archivo','Helvetica Neue',Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef5fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
                    @include('emails.partials.logo-header')
                    <tr>
                        <td style="background-color:#ffffff;padding:40px;border-left:1px solid #d8e7f5;border-right:1px solid #d8e7f5;">
                            <h1 style="margin:0 0 16px;color:#001d3a;font-size:24px;font-weight:700;">
                                Welcome, {{ $name }}
                            </h1>
                            <p style="margin:0 0 12px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                Your Nilo account is ready. Create invoices, quotations, and receipts, and manage them all from your dashboard.
                            </p>
                            <p style="margin:0 0 28px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                First, confirm your email address using the button below.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                                <tr>
                                    <td style="border-radius:8px;background-color:#00417d;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;">
                                            Verify email address
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px;color:#8494a7;font-size:13px;line-height:1.6;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>
                            <p style="margin:0 0 24px;word-break:break-all;">
                                <a href="{{ $url }}" style="color:#00417d;font-size:13px;">{{ $url }}</a>
                            </p>
                            <p style="margin:0;color:#8494a7;font-size:13px;line-height:1.6;">
                                If you did not create a Nilo account, you can ignore this email.
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
