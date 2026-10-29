<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Question #{{ $questionId }} Modified</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;-webkit-font-smoothing:antialiased">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05),0 2px 4px -2px rgba(0,0,0,0.05);border:1px solid #e2e8f0">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background:#0f172a;padding:28px 32px;border-bottom:3px solid #0284c7">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="font-size:20px;font-weight:800;letter-spacing:-0.5px;color:#ffffff">
                                            MY MERCHANT <span style="color:#38bdf8">NAVY</span>
                                        </div>
                                        <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-top:4px">
                                            Academic Quality & Question Correction Audit
                                        </div>
                                    </td>
                                    <td align="right">
                                        <span style="display:inline-block;background:rgba(2,132,199,0.2);border:1px solid #0284c7;color:#7dd3fc;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px">
                                            Question #{{ $questionId }}
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
                                Question Correction Notification
                            </h2>
                            <p style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.5">
                                An academic faculty member or administrator has actioned a student-reported issue and modified a test question.
                            </p>

                            <!-- Audit Overview Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:24px">
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;width:35%;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Test / Subject
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:700;color:#0f172a">
                                        {{ $testTitle }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Actioned By
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#0f172a">
                                        {{ $adminName }} ({{ $adminEmail }})
                                        <span style="display:inline-block;margin-left:6px;background:#e2e8f0;color:#475569;font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;text-transform:uppercase">{{ $adminRole }}</span>
                                    </td>
                                </tr>
                                @if(!empty($studentInfo))
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Reported By Student
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#0f172a">
                                        {{ $studentInfo }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Report Reason
                                    </td>
                                    <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#b91c1c">
                                        {{ $reason }}
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:14px 20px;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">
                                        Audit Timestamp
                                    </td>
                                    <td style="padding:14px 20px;font-size:13px;color:#475569">
                                        {{ $timestamp }} UTC (IP: {{ $ipAddress }})
                                    </td>
                                </tr>
                            </table>

                            <!-- Question Body -->
                            <div style="margin-bottom:24px">
                                <div style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                                    Question Body:
                                </div>
                                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;font-size:14px;line-height:1.6;color:#1e293b">
                                    {!! nl2br(e($qBody)) !!}
                                </div>
                            </div>

                            <!-- Options Breakdown -->
                            @if(!empty($options))
                            <div style="margin-bottom:24px">
                                <div style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                                    Answer Options & Evaluation:
                                </div>
                                <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
                                    @foreach($options as $opt)
                                    <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;background:{{ !empty($opt['is_correct']) ? '#f0fdf4' : (!empty($opt['was_student_pick']) ? '#eff6ff' : '#ffffff') }};display:flex;align-items:center;justify-content:space-between">
                                        <div style="font-size:13px;color:#1e293b;line-height:1.4">
                                            <strong style="margin-right:6px">({{ $opt['letter'] }})</strong> {{ $opt['body'] }}
                                        </div>
                                        <div style="margin-left:12px;white-space:nowrap">
                                            @if(!empty($opt['is_correct']))
                                                <span style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;font-size:10px;font-weight:700;padding:3px 8px;border-radius:6px;text-transform:uppercase">
                                                    ✓ Correct Answer Key
                                                </span>
                                            @endif
                                            @if(!empty($opt['was_student_pick']))
                                                <span style="background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;font-size:10px;font-weight:700;padding:3px 8px;border-radius:6px;text-transform:uppercase;margin-left:4px">
                                                    👤 Student Picked
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <!-- Changes Made Box -->
                            <div style="margin-bottom:28px">
                                <div style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">
                                    Correction Details & Faculty Remarks:
                                </div>
                                <div style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:8px;padding:16px 20px;font-size:14px;line-height:1.6;color:#92400e;white-space:pre-wrap">{{ $changes }}</div>
                            </div>

                            <!-- Dashboard Link Button -->
                            <div style="text-align:center;margin-bottom:20px">
                                <a href="{{ $dashboardUrl ?? 'https://api.mymerchantnavy.com/admin/question-reports' }}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:12px 24px;border-radius:10px">
                                    Open Question Report in Admin Dashboard &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e2e8f0;text-align:center">
                            <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.5">
                                My Merchant Navy Academic Administration Portal<br>
                                Automated Notification & Audit System
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

