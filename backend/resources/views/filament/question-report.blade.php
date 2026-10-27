{{-- One question report: what the student said, the question as students see it (correct answer and their pick marked), and the reply. --}}
@php($s = $report->status)
<div style="display:flex;flex-direction:column;gap:1rem">
    <div style="border:1px solid var(--warning-300);background:rgba(245,158,11,.07);border-radius:.75rem;padding:1rem">
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between">
            <b>{{ $report->reason->getLabel() }}</b>
            <span class="cb-muted">{{ $report->user?->name }} · {{ $report->user?->loginId() }} · {{ $report->created_at->format('d M Y, h:i A') }}</span>
        </div>
        <p style="margin-top:.5rem;white-space:pre-line">{{ $report->details ?: 'The student did not add details.' }}</p>
        @if ($report->test)
            <p class="cb-muted" style="margin-top:.5rem">Test: {{ $report->test->title }}</p>
        @endif
    </div>

    @if ($body === null)
        <p class="cb-muted">This question was deleted.</p>
    @else
        <div style="border:1px solid var(--gray-200);border-radius:.75rem;padding:1rem;background:var(--gray-50)">
            <div class="cb-math" style="font-size:1rem;line-height:1.6">{!! $body !!}</div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.5rem;margin-top:.75rem">
                @foreach ($options as $n => $text)
                    @php($isCorrect = (int) $correct === (int) $n)
                    @php($isChosen = (int) $chosen === (int) $n)
                    <div style="display:flex;gap:.5rem;align-items:baseline;border:1px solid {{ $isCorrect ? 'var(--success-500)' : ($isChosen ? 'var(--danger-400)' : 'var(--gray-200)') }};background:{{ $isCorrect ? 'rgba(34,197,94,.08)' : ($isChosen ? 'rgba(239,68,68,.06)' : '#fff') }};border-radius:.5rem;padding:.5rem .75rem">
                        <b class="cb-muted" style="flex:none">{{ chr(64 + $n) }}</b>
                        <span class="cb-math" style="min-width:0">{{ $text }}</span>
                        <span class="cb-muted" style="margin-left:auto;flex:none">
                            @if ($isCorrect)<span style="color:var(--success-600)">✓ key</span>@endif
                            @if ($isChosen)<span style="color:{{ $isCorrect ? 'var(--success-600)' : 'var(--danger-600)' }}"> · student</span>@endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if (filled(trim(strip_tags((string) $explanation))))
                <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px dashed var(--gray-300)">
                    <b class="cb-muted">Solution</b>
                    <div class="cb-math" style="line-height:1.6">{!! $explanation !!}</div>
                </div>
            @endif
        </div>
    @endif

    @if ($s !== \App\Enums\ReportStatus::Open)
        <div style="border:1px solid var(--gray-200);border-radius:.75rem;padding:1rem">
            <b>{{ $s->getLabel() }}</b>
            <span class="cb-muted">· {{ $report->resolver?->name ?? 'Admin' }} · {{ $report->resolved_at?->format('d M Y, h:i A') }}</span>
            <p style="margin-top:.5rem;white-space:pre-line">{{ $report->admin_note ?: 'No reply was written.' }}</p>
        </div>
    @endif
</div>
