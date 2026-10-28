{{-- One question report: what the student said, the question as students see it (correct answer and their pick marked), and the reply. --}}
@php($s = $report->status)
<div style="display:flex;flex-direction:column;gap:1.25rem;font-family:inherit;">
    {{-- Report Header Box --}}
    <div style="border:1px solid #fcd34d;background:#fffbeb;border-radius:0.75rem;padding:1.25rem;">
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="display:inline-flex;align-items:center;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:700;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">
                    🚩 {{ $report->reason->getLabel() }}
                </span>
                <span style="font-size:0.875rem;font-weight:600;color:#92400e;">
                    Question #{{ $report->question_id }}
                </span>
            </div>
            <span style="font-size:0.8125rem;color:#b45309;">
                Reported {{ $report->created_at->format('d M Y, h:i A') }} ({{ $report->created_at->diffForHumans() }})
            </span>
        </div>

        <div style="font-size:0.875rem;color:#78350f;margin-bottom:0.5rem;">
            <b>Student:</b> {{ $report->user?->name ?: 'Student #'.$report->user_id }} 
            <span style="color:#b45309;">({{ $report->user?->email ?: 'No email' }})</span>
            @if ($report->test)
                · <b>Test:</b> <span style="font-weight:600;">{{ $report->test->title }}</span>
            @endif
        </div>

        <div style="margin-top:0.75rem;padding:0.75rem 1rem;background:#ffffff;border:1px solid #fef3c7;border-radius:0.5rem;">
            <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#b45309;margin-bottom:0.25rem;">
                💬 Student Remarks / Issue Details:
            </div>
            <p style="margin:0;font-size:0.9375rem;color:#1e293b;white-space:pre-line;line-height:1.5;">
                {{ $report->details ?: 'No additional details provided by student.' }}
            </p>
        </div>
    </div>

    {{-- Question & Answer Key Box --}}
    @if ($question === null)
        <div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:0.75rem;padding:1.5rem;text-align:center;color:#64748b;">
            This question was removed or deleted from the question bank.
        </div>
    @else
        <div style="border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.25rem;background:#ffffff;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;border-bottom:1px solid #f1f5f9;padding-bottom:0.75rem;">
                <span style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;">
                    Question Text
                </span>
                <span style="font-size:0.75rem;color:#94a3b8;">
                    ID: #{{ $question->id }}
                </span>
            </div>

            <div class="cb-math" style="font-size:1.0625rem;font-weight:500;color:#0f172a;line-height:1.65;margin-bottom:1.25rem;">
                {!! $body !!}
            </div>

            <div style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:0.5rem;">
                Answer Choices & Key Status:
            </div>

            <div style="display:flex;flex-direction:column;gap:0.625rem;">
                @foreach ($optionsList as $idx => $opt)
                    @php($letter = chr(65 + $idx))
                    @php($isKey = (bool) $opt->is_correct)
                    @php($isStudentPick = $opt->id === $chosenOptionId)

                    @php($cardBorder = $isKey ? '#10b981' : ($isStudentPick ? '#ef4444' : '#e2e8f0'))
                    @php($cardBg = $isKey ? '#f0fdf4' : ($isStudentPick ? '#fef2f2' : '#ffffff'))
                    @php($badgeBg = $isKey ? '#059669' : ($isStudentPick ? '#dc2626' : '#64748b'))

                    <div style="display:flex;align-items:flex-start;gap:0.75rem;border:2px solid {{ $cardBorder }};background:{{ $cardBg }};border-radius:0.625rem;padding:0.75rem 1rem;transition:all 0.15s ease;">
                        {{-- Option Circle Letter --}}
                        <div style="flex-shrink:0;width:1.75rem;height:1.75rem;border-radius:9999px;background:{{ $badgeBg }};color:#ffffff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8125rem;margin-top:0.125rem;">
                            {{ $letter }}
                        </div>

                        {{-- Option Body --}}
                        <div class="cb-math" style="flex:1;min-width:0;font-size:0.9375rem;color:#0f172a;line-height:1.5;">
                            {!! $opt->body !!}
                        </div>

                        {{-- Badges for Key and Student Pick --}}
                        <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:0.25rem;">
                            @if ($isKey)
                                <span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:700;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;">
                                    ✓ OFFICIAL KEY
                                </span>
                            @endif

                            @if ($isStudentPick)
                                <span style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.25rem 0.625rem;border-radius:9999px;font-size:0.6875rem;font-weight:700;background:{{ $isKey ? '#dbeafe' : '#fee2e2' }};color:{{ $isKey ? '#1e40af' : '#991b1b' }};border:1px solid {{ $isKey ? '#bfdbfe' : '#fecaca' }};">
                                    👤 STUDENT PICK
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Explanation Box --}}
            @if (filled(trim(strip_tags((string) $explanation))))
                <div style="margin-top:1.25rem;padding:1rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:0.625rem;">
                    <div style="display:flex;align-items:center;gap:0.375rem;font-size:0.8125rem;font-weight:700;color:#0369a1;margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:0.05em;">
                        💡 Official Solution & Explanation:
                    </div>
                    <div class="cb-math" style="font-size:0.9375rem;color:#0c4a6e;line-height:1.6;">
                        {!! $explanation !!}
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Resolution Audit Log --}}
    @if ($s !== \App\Enums\ReportStatus::Open)
        <div style="border:1px solid #cbd5e1;background:#f8fafc;border-radius:0.75rem;padding:1.25rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                <span style="font-weight:700;font-size:0.875rem;color:#334155;">
                    Status: <span style="color:{{ $s === \App\Enums\ReportStatus::Resolved ? '#059669' : '#64748b' }};">{{ $s->getLabel() }}</span>
                </span>
                <span style="font-size:0.8125rem;color:#64748b;">
                    By {{ $report->resolver?->name ?? 'Administrator' }} · {{ $report->resolved_at?->format('d M Y, h:i A') }}
                </span>
            </div>
            @if ($report->admin_note)
                <div style="margin-top:0.5rem;font-size:0.875rem;color:#1e293b;background:#ffffff;padding:0.75rem 1rem;border-radius:0.5rem;border:1px solid #e2e8f0;white-space:pre-line;">
                    <b>Resolution Reply to Student:</b><br>
                    {{ $report->admin_note }}
                </div>
            @endif
        </div>
    @endif
</div>
