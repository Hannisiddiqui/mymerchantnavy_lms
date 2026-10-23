<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your login code</title>
</head>
<body style="margin:0;background:#f5f7fb;font-family:Inter,'Segoe UI',Arial,sans-serif;color:#1e293b">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 12px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:460px;background:#ffffff;border-radius:16px;border:1px solid #e5e9f0">
                    <tr>
                        <td style="padding:28px 28px 8px">
                            <div style="font-size:18px;font-weight:700;color:#0b1e3d">My Merchant <span style="color:#2563eb">Navy</span></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 0">
                            <p style="margin:0 0 6px;font-size:20px;font-weight:700;color:#0b1e3d">Your login code</p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#475569">
                                Use this code to log in. It is valid for {{ $minutes }} minutes.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:22px 28px">
                            <div style="display:inline-block;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 28px;font-size:32px;letter-spacing:8px;font-weight:700;color:#1d4ed8">{{ $code }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b">
                                Do not share this code with anyone. If you did not try to log in, you can ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
