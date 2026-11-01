<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;background:#f5f7fb;font-family:Inter,'Segoe UI',Arial,sans-serif;color:#1e293b">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 12px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid #e5e9f0">
                <tr><td style="padding:26px 30px 6px">
                    <div style="font-size:18px;font-weight:700;color:#0b1e3d">My Merchant <span style="color:#2563eb">Navy</span></div>
                </td></tr>
                <tr><td style="padding:10px 30px 0">
                    <p style="margin:0;font-size:21px;font-weight:700;color:#0b1e3d">{{ $heading }}</p>
                </td></tr>
                <tr><td style="padding:14px 30px 28px;font-size:15px;line-height:1.65;color:#334155">
                    {!! $bodyHtml !!}
                </td></tr>
            </table>
            <p style="margin:14px 0 0;font-size:12px;color:#94a3b8">© {{ date('Y') }} My Merchant Navy</p>
        </td></tr>
    </table>
</body>
</html>
