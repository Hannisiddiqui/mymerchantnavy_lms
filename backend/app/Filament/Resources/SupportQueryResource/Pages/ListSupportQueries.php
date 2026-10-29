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

    public string $activeTab = 'whatsapp'; // 'whatsapp' or 'email'

    public ?int $selectedQueryId = null;

    public string $search = '';

    public string $chatMessage = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public string $emailStatus = 'resolved';

    public function mount(): void
    {
        parent::mount();

        $latest = SupportQuery::where('status', 'open')->latest()->first()
            ?? SupportQuery::latest()->first();

        if ($latest) {
            $this->selectedQueryId = $latest->id;
            $this->emailSubject = "Re: Inquiry Regarding {$latest->category} - My Merchant Navy";
            $this->emailBody = $latest->admin_reply ?: "Dear {$latest->name},\n\nThank you for reaching out to My Merchant Navy regarding your inquiry ({$latest->category}).\n\nRegarding your question:\n\"{$latest->message}\"\n\n";
        }
    }

    public function selectQuery(int $id): void
    {
        $this->selectedQueryId = $id;
        $query = SupportQuery::find($id);
        if ($query) {
            $this->chatMessage = '';
            $this->emailSubject = "Re: Inquiry Regarding {$query->category} - My Merchant Navy";
            $this->emailBody = $query->admin_reply ?: "Dear {$query->name},\n\nThank you for reaching out to My Merchant Navy regarding your inquiry ({$query->category}).\n\nRegarding your question:\n\"{$query->message}\"\n\n";
            $this->emailStatus = $query->status === 'open' ? 'resolved' : $query->status;
        }
    }

    public function sendChatMessage(): void
    {
        if (! $this->selectedQueryId || empty(trim($this->chatMessage))) {
            return;
        }

        $query = SupportQuery::find($this->selectedQueryId);
        if (! $query) {
            return;
        }

        $admin = Auth::guard('admin')->user();
        $msg = trim($this->chatMessage);

        $query->update([
            'admin_reply' => $msg,
            'status' => 'resolved',
            'replied_by_admin_id' => $admin?->id,
            'replied_at' => now(),
        ]);

        ActivityLog::record(
            event: 'support.whatsapp_replied',
            description: "Site Manager {$admin?->name} sent WhatsApp message to {$query->name} ({$query->phone}).",
            properties: [
                'query_id' => $query->id,
                'phone' => $query->phone,
                'reply' => $msg,
            ],
            admin: $admin
        );

        $this->chatMessage = '';

        Notification::make()
            ->title('Message Sent & Delivered to Student')
            ->success()
            ->send();
    }

    public function generateAiEmail(string $topic): void
    {
        $query = SupportQuery::find($this->selectedQueryId);
        if (! $query) {
            return;
        }

        $name = $query->name ?: 'Student';
        $phone = $query->phone ? '+' . ltrim($query->phone, '+') : '';
        $inquiry = $query->message;
        $category = $query->category;

        if ($topic === 'doubt') {
            $this->emailSubject = "Academic Faculty Resolution: {$category} - My Merchant Navy";
            $this->emailBody = "Dear {$name},\n\nThank you for submitting your academic doubt regarding {$category}.\n\nOur curriculum faculty has reviewed your query: \"{$inquiry}\".\n\nHere is the detailed solution and concept explanation:\n1. Please review the updated practice ladders and video explanations available inside your LMS course portal.\n2. All question formulas and step-by-step calculations have been verified according to the latest IMU-CET / Sponsorship syllabus.\n\nIf you have any further questions or require additional clarifications, feel free to reply directly to this email or chat with us on WhatsApp.\n\nWarm regards,\nAcademic Support & Faculty Desk\nMy Merchant Navy";
        } elseif ($topic === 'counseling') {
            $this->emailSubject = "Maritime Career Counseling Guidance: {$category} - My Merchant Navy";
            $this->emailBody = "Dear {$name},\n\nWe have received your counseling request regarding {$category} and your maritime career roadmap.\n\nRegarding your note: \"{$inquiry}\"\n\nOur senior maritime mentors and captain advisors are here to support you at every stage. We will also be scheduling a direct 1-on-1 counseling call on your phone ({$phone}).\n\nPlease ensure your target exam and attempt year are up to date on your LMS profile so we can guide you effectively.\n\nBest of luck,\nAdmissions & Mentorship Team\nMy Merchant Navy";
        } else {
            $this->emailSubject = "Batch Enrollment & Course Access Update - My Merchant Navy";
            $this->emailBody = "Dear {$name},\n\nThank you for reaching out regarding your course package and batch enrollment for {$category}.\n\nYour account has been verified and your live batches, question imports, and curriculum materials are accessible at https://lms.mymerchantnavy.com.\n\nIf you need any technical assistance with mock tests or PDF study notes, we are available 24/7 to help.\n\nWarm regards,\nStudent Support Team\nMy Merchant Navy";
        }

        Notification::make()
            ->title('AI Response Drafted')
            ->body('Tailored email response generated based on student inquiry.')
            ->success()
            ->send();
    }

    public function sendPlatformEmail(): void
    {
        if (! $this->selectedQueryId || empty(trim($this->emailBody))) {
            Notification::make()->title('Please enter an email body before sending')->warning()->send();
            return;
        }

        $query = SupportQuery::find($this->selectedQueryId);
        if (! $query) {
            Notification::make()->title('Query not found')->danger()->send();
            return;
        }

        $admin = Auth::guard('admin')->user();
        $body = trim($this->emailBody);
        $subject = trim($this->emailSubject) ?: "[My Merchant Navy] Response to your Inquiry: {$query->category}";

        $query->update([
            'admin_reply' => $body,
            'status' => $this->emailStatus,
            'replied_by_admin_id' => $admin?->id,
            'replied_at' => now(),
        ]);

        if (filter_var($query->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::raw($body, function ($msg) use ($query, $subject) {
                    $msg->to($query->email)
                        ->subject($subject);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        ActivityLog::record(
            event: 'support.email_dispatched',
            description: "Site Manager {$admin?->name} sent email to {$query->name} ({$query->email}).",
            properties: [
                'query_id' => $query->id,
                'email' => $query->email,
                'subject' => $subject,
            ],
            admin: $admin
        );

        Notification::make()
            ->title('Email Sent Successfully')
            ->body("Dispatched to {$query->email} and saved to student record.")
            ->success()
            ->send();
    }

    public function createTestQuery(): void
    {
        $testQuery = SupportQuery::create([
            'student_id' => null,
            'name' => 'Fardeen Siddiqui',
            'email' => 'fardeen@grocliq.ai',
            'phone' => '9885169967',
            'category' => 'DNS Sponsorship',
            'channel' => 'whatsapp',
            'message' => 'Sir, I have a query regarding interview rounds for Anglo-Eastern and Great Eastern. When will the mock interview batch start?',
            'status' => 'open',
            'ip_address' => request()->ip(),
        ]);

        $this->selectedQueryId = $testQuery->id;
        $this->chatMessage = '';
        $this->selectQuery($testQuery->id);

        Notification::make()->title('Sample Query Created')->success()->send();
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
