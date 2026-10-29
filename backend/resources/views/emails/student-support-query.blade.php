<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $category }} from {{ $name }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;-webkit-font-smoothing:antialiased">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05),0 2px 4px -2px rgba(0,0,0,0.05);border:1px solid #e2e8f0">
                    <!-- Brand Navy Header -->
                    <tr>
                        <td style="background:#0f172a;padding:28px 32px;border-bottom:3px solid #2563eb">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="font-size:20px;font-weight:800;letter-spacing:-0.5px;color:#ffffff">
                                            MY MERCHANT <span style="color:#38bdf8">NAVY</span>
                                        </div>
                                        <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-top:4px">
                                            Student Academic Helpdesk & Inquiry
                                        </div>
                                    </td>
                                    <td align="right">
                                        <span style="display:inline-block;background:rgba(37,99,235,0.2);border:1px solid #3b82f6;color:#93c5fd;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px">
                                            {{ $category }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:32px">
                            <h2 style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a">
                                New Student Query Received
                            </h2>
                            <p style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.5">
                                A student submitted an inquiry via the in-app support chatbot. Details and student contact info are below:
                            </p>

                            <!-- Student Info Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:24px">
                                <tr>
                                    <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;width:35%;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Student Name
                                    </td>
                                    <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:700;color:#0f172a">
                                        {{ $name }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Email Address
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:600;color:#2563eb">
                                        <a href="mailto:{{ $email }}" style="color:#2563eb;text-decoration:none">{{ $email }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        WhatsApp / Mobile
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:600;color:#0f172a">
                                        {{ $phone }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 20px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Query Category
                                    </td>
                                    <td style="padding:14px 20px;font-size:14px;font-weight:600;color:#0f172a">
                                        {{ $category }}
                                    </td>
                                </tr>
                            </table>

                            <!-- Message Box -->
                            <div style="margin-bottom:28px">
                                <div style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                                    Student Query / Message:
                                </div>
                                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:8px;padding:18px 20px;font-size:14px;line-height:1.6;color:#0c4a6e;white-space:pre-wrap">{{ $msg }}</div>
                            </div>

                            <!-- Action Buttons -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
                                <tr>
                                    <td align="center" style="padding-right:8px;width:50%">
                                        <a href="mailto:{{ $email }}?subject=Re:%20[MMN%20Support]%20{{ rawurlencode($category) }}" style="display:block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 18px;border-radius:10px;text-align:center">
                                            ✉️ Reply to Student
                                        </a>
                                    </td>
                                    @if(!empty($cleanPhone))
                                    <td align="center" style="padding-left:8px;width:50%">
                                        <a href="https://wa.me/{{ $cleanPhone }}?text={{ rawurlencode('Hello ' . $name . ', regarding your query on My Merchant Navy:') }}" style="display:block;background:#16a34a;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 18px;border-radius:10px;text-align:center">
                                            💬 Reply on WhatsApp
                                        </a>
                                    </td>
                                    @endif
                                </tr>
                            </table>

                            <!-- Metadata / Device Details -->
                            <div style="background:#f8fafc;border-radius:8px;padding:12px 16px;border:1px solid #e2e8f0;font-size:11px;color:#64748b;line-height:1.6">
                                <div><strong>IP Address:</strong> {{ $ip ?? 'Unknown' }}</div>
                                <div><strong>Device:</strong> {{ $device ?? 'Unknown' }}</div>
                                <div><strong>Logged At:</strong> {{ $timestamp ?? date('Y-m-d H:i:s') }} UTC</div>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;text-align:center">
                            <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.5">
                                This email was generated automatically by the My Merchant Navy LMS Support Desk.<br>
                                Direct student replies can be dispatched by replying directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

