<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Welcome to Nilo</title>
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
                                Welcome aboard, {{ $name }}!
                            </h1>
                            <p class="email-text" style="margin:0 0 12px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                Thank you for your support. We are thrilled to have you. Your paperwork is about to get a serious glow-up.
                            </p>
                            <p class="email-text" style="margin:0 0 20px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                To get started with Nilo, follow the three steps below:
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 28px;">
                                <tr>
                                    <td width="40" valign="top" style="padding:0 14px 18px 0;">
                                        <div style="width:28px;height:28px;border-radius:14px;background-color:#00417d;color:#ffffff;font-size:14px;font-weight:700;text-align:center;line-height:28px;">1</div>
                                    </td>
                                    <td class="email-text" valign="top" style="padding:0 0 18px;color:#3d4a5c;font-size:15px;line-height:1.5;">
                                        Log in to your dashboard. You are basically already here.
                                    </td>
                                </tr>
                                <tr>
                                    <td width="40" valign="top" style="padding:0 14px 18px 0;">
                                        <div style="width:28px;height:28px;border-radius:14px;background-color:#00417d;color:#ffffff;font-size:14px;font-weight:700;text-align:center;line-height:28px;">2</div>
                                    </td>
                                    <td class="email-text" valign="top" style="padding:0 0 18px;color:#3d4a5c;font-size:15px;line-height:1.5;">
                                        Create your company or organisation and pick a template theme that feels like you.
                                    </td>
                                </tr>
                                <tr>
                                    <td width="40" valign="top" style="padding:0 14px 0 0;">
                                        <div style="width:28px;height:28px;border-radius:14px;background-color:#00417d;color:#ffffff;font-size:14px;font-weight:700;text-align:center;line-height:28px;">3</div>
                                    </td>
                                    <td class="email-text" valign="top" style="padding:0;color:#3d4a5c;font-size:15px;line-height:1.5;">
                                        Start filling your repository with invoices, quotations, receipts, and everything in between.
                                    </td>
                                </tr>
                            </table>
                            <p class="email-text" style="margin:0 0 28px;color:#3d4a5c;font-size:15px;line-height:1.6;">
                                That is the whole tour. Welcome to Nilo.
                            </p>
                            <img src="{{ isset($message) ? $message->embed(public_path('pointer-hand.svg')) : asset('pointer-hand.svg') }}" width="40" height="40" alt="" style="display:block;border:0;outline:none;text-decoration:none;margin:0 0 -6px 26px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                                <tr>
                                    <td style="border-radius:8px;background-color:#00417d;">
                                        <a href="{{ route('dashboard') }}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:8px;">
                                            <img src="{{ isset($message) ? $message->embed(public_path('icon-dashboard.png')) : asset('icon-dashboard.png') }}" width="16" height="16" alt="" style="display:inline-block;vertical-align:middle;margin-right:8px;border:0;"><span style="vertical-align:middle;">Dashboard</span>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p class="email-muted" style="margin:0;color:#8494a7;font-size:13px;line-height:1.6;">
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
