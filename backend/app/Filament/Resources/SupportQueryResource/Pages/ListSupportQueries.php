<?php

namespace App\Filament\Resources\SupportQueryResource\Pages;

use App\Filament\Resources\SupportQueryResource;
use App\Models\ActivityLog;
use App\Models\SupportQuery;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ListSupportQueries extends ListRecords
{
    protected static string $resource = SupportQueryResource::class;

    protected string $view = 'filament.resources.support-queries-inbox';

    public string $viewMode = 'chat'; // 'chat' or 'table'

    public ?int $selectedQueryId = null;

    public string $search = '';

    public string $filter = 'all'; // 'all', 'open', 'whatsapp', 'replied'

    public string $replyText = '';

    public string $replyStatus = 'resolved';

    public bool $sendEmail = true;

    public function mount(): void
    {
        parent::mount();

        $latest = SupportQuery::where('status', 'open')->latest()->first()
            ?? SupportQuery::latest()->first();

        if ($latest) {
            $this->selectedQueryId = $latest->id;
            $this->replyText = $latest->admin_reply ?? '';
        }
    }

    public function selectQuery(int $id): void
    {
        $this->selectedQueryId = $id;
        $query = SupportQuery::find($id);
        $this->replyText = $query?->admin_reply ?? '';
        $this->replyStatus = ($query && $query->status === 'open') ? 'resolved' : ($query?->status ?? 'resolved');
    }

    public function setQuickReply(string $type): void
    {
        $query = SupportQuery::find($this->selectedQueryId);
        $name = $query?->name ?? 'Student';
        $phone = $query?->phone ? '+' . ltrim($query->phone, '+') : '';
        $category = $query?->category ?? 'course inquiry';

        $templates = [
            'greeting' => "Hello {$name}, thank you for contacting My Merchant Navy. We have received your inquiry regarding {$category}. How can we assist you with your preparation?",
            'resolved' => "Hello {$name}, your doubt regarding {$category} has been reviewed and resolved by our academic faculty. Please check your LMS portal for the updated materials.",
            'call' => "Hello {$name}, our senior maritime counselor will be calling you shortly on your number {$phone} to provide complete 1-on-1 guidance.",
            'payment' => "Hello {$name}, your course enrollment has been verified and your student LMS account is fully unlocked. Wishing you all the very best for your exams!",
        ];

        if (isset($templates[$type])) {
            $this->replyText = $templates[$type];
        }
    }

    public function submitReply(): void
    {
        if (! $this->selectedQueryId || empty(trim($this->replyText))) {
            Notification::make()->title('Please enter a reply message before sending')->warning()->send();
            return;
        }

        $query = SupportQuery::find($this->selectedQueryId);
        if (! $query) {
            Notification::make()->title('Query not found')->danger()->send();
            return;
        }

        $admin = Auth::guard('admin')->user();
        $reply = trim($this->replyText);

        $query->update([
            'admin_reply' => $reply,
            'status' => $this->replyStatus,
            'replied_by_admin_id' => $admin?->id,
            'replied_at' => now(),
        ]);

        if ($this->sendEmail && filter_var($query->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::send('emails.support-reply', [
                    'studentName' => $query->name,
                    'category' => $query->category,
                    'originalMessage' => $query->message,
                    'replyMessage' => $reply,
                    'facultyName' => $admin?->name ?? 'Academic Support Team',
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                ], function ($msg) use ($query) {
                    $msg->to($query->email)
                        ->subject("[My Merchant Navy] Response to your Inquiry: {$query->category}");
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        ActivityLog::record(
            event: 'support.query_replied',
            description: "Site Manager {$admin?->name} replied to student {$query->name} (Phone: {$query->phone}).",
            properties: [
                'query_id' => $query->id,
                'student_name' => $query->name,
                'student_phone' => $query->phone,
                'status' => $this->replyStatus,
            ],
            admin: $admin
        );

        Notification::make()
            ->title('Reply Dispatched Successfully')
            ->body("Response recorded and sent to {$query->name}.")
            ->success()
            ->send();
    }

    public function updateStatus(string $status): void
    {
        if (! $this->selectedQueryId) {
            return;
        }

        $query = SupportQuery::find($this->selectedQueryId);
        if ($query) {
            $query->update(['status' => $status]);
            $this->replyStatus = $status;
            Notification::make()->title('Status updated to ' . ucfirst(str_replace('_', ' ', $status)))->success()->send();
        }
    }

    public function createTestQuery(): void
    {
        $testQuery = SupportQuery::create([
            'student_id' => null,
            'name' => 'Aditya Sharma',
            'email' => 'aditya.sharma.test@gmail.com',
            'phone' => '9812345678',
            'category' => 'DNS Sponsorship',
            'channel' => 'whatsapp',
            'message' => 'Sir, I want to know about the upcoming DNS Sponsorship batch, interview ladder questions, and company sponsorship tests for Anglo-Eastern.',
            'status' => 'open',
            'ip_address' => request()->ip(),
        ]);

        $this->selectedQueryId = $testQuery->id;
        $this->replyText = '';
        $this->viewMode = 'chat';

        Notification::make()->title('Sample Student Inquiry Generated')->success()->send();
    }

    public function getQueriesProperty(): Collection
    {
        return SupportQuery::query()
            ->when($this->search, function ($q) {
                $term = trim($this->search);
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('message', 'like', "%{$term}%");
                });
            })
            ->when($this->filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($this->filter === 'whatsapp', fn ($q) => $q->where('channel', 'whatsapp'))
            ->when($this->filter === 'replied', fn ($q) => $q->whereNotNull('admin_reply'))
            ->latest()
            ->get();
    }

    public function getSelectedQueryProperty(): ?SupportQuery
    {
        if (! $this->selectedQueryId) {
            return null;
        }

        return SupportQuery::find($this->selectedQueryId);
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => SupportQuery::count(),
            'open' => SupportQuery::where('status', 'open')->count(),
            'whatsapp' => SupportQuery::where('channel', 'whatsapp')->count(),
            'resolved' => SupportQuery::where('status', 'resolved')->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
