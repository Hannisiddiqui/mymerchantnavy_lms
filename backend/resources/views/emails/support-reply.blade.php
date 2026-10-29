<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Response to your Inquiry - My Merchant Navy</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;-webkit-font-smoothing:antialiased">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border:1px solid #e2e8f0">
                    <!-- Header -->
                    <tr>
                        <td style="background:#0f172a;padding:24px 32px;border-bottom:3px solid #2563eb">
                            <div style="font-size:20px;font-weight:800;letter-spacing:-0.5px;color:#ffffff">
                                MY MERCHANT <span style="color:#38bdf8">NAVY</span>
                            </div>
                            <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-top:4px">
                                Academic Support & Doubt Resolution
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:32px">
                            <h2 style="margin:0 0 12px;font-size:18px;font-weight:700;color:#0f172a">
                                Hello {{ $studentName }},
                            </h2>
                            <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.6">
                                Our academic support team has reviewed your inquiry regarding <strong>{{ $category }}</strong>.
                            </p>

                            <!-- Response Box -->
                            <div style="margin-bottom:24px">
                                <div style="font-size:12px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                                    Response from {{ $facultyName }}:
                                </div>
                                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-left:4px solid #2563eb;border-radius:8px;padding:16px 20px;font-size:14px;line-height:1.6;color:#1e3a8a;white-space:pre-wrap">{{ $replyMessage }}</div>
                            </div>

                            <!-- Original Doubt Box -->
                            <div style="margin-bottom:24px">
                                <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                                    Your Original Inquiry:
                                </div>
                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;font-size:13px;line-height:1.5;color:#475569;white-space:pre-wrap">{{ $originalMessage }}</div>
                            </div>

                            <p style="margin:0 0 24px;font-size:13px;color:#64748b;line-height:1.5">
                                You can also view this response inside your in-app chat widget on the student portal anytime.
                            </p>

                            <div style="text-align:center">
                                <a href="https://lms.mymerchantnavy.com" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 24px;border-radius:10px">
                                    Open Student LMS &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;text-align:center;font-size:12px;color:#94a3b8">
                            My Merchant Navy &bull; Official Student Support &bull; <a href="https://mymerchantnavy.com" style="color:#64748b;text-decoration:underline">mymerchantnavy.com</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

