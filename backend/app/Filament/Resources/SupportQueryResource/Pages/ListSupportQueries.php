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

    public ?string $activeTab = 'whatsapp'; // 'whatsapp' or 'email'

    public ?int $selectedQueryId = null;

    public string $search = '';

    public string $statusFilter = 'all'; // 'all', 'open', 'resolved'

    public string $chatMessage = '';

    // Editable email fields
    public string $recipientEmail = '';

    public string $recipientName = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public string $emailStatus = 'resolved';

    public function mount(): void
    {
        parent::mount();

        $latest = SupportQuery::where('status', 'open')->latest()->first()
            ?? SupportQuery::latest()->first();

        if ($latest) {
            $this->selectQuery($latest->id);
        } else {
            $this->newEmailCompose();
        }
    }

    public function selectQuery(int $id): void
    {
        $this->selectedQueryId = $id;
        $query = SupportQuery::find($id);
        if ($query) {
            $this->chatMessage = '';
            $this->recipientEmail = $query->email ?: '';
            $this->recipientName = $query->name ?: '';
            $this->emailSubject = "Re: Inquiry Regarding {$query->category} - My Merchant Navy";
            $this->emailBody = $query->admin_reply ?: "Dear {$query->name},\n\nThank you for reaching out to My Merchant Navy regarding your inquiry ({$query->category}).\n\nRegarding your question:\n\"{$query->message}\"\n\n";
            $this->emailStatus = $query->status === 'open' ? 'resolved' : $query->status;
        }
    }

    public function newEmailCompose(): void
    {
        $this->selectedQueryId = null;
        $this->recipientEmail = '';
        $this->recipientName = '';
        $this->emailSubject = 'My Merchant Navy - Academic & Course Enrollment Update';
        $this->emailBody = "Dear Student,\n\nWe are writing to you from My Merchant Navy regarding your maritime exam preparation and course updates.\n\n";
        $this->emailStatus = 'resolved';
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
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

        $newReply = filled($query->admin_reply)
            ? $query->admin_reply . "\n\n" . $msg
            : $msg;

        $query->update([
            'admin_reply' => $newReply,
            'status' => 'resolved',
            'replied_by_admin_id' => $admin?->id,
            'replied_at' => now(),
        ]);

        ActivityLog::record(
            event: 'support.whatsapp_replied',
            description: "Site Manager {$admin?->name} replied on WhatsApp to {$query->name} ({$query->phone}).",
            properties: [
                'query_id' => $query->id,
                'phone' => $query->phone,
                'reply' => $msg,
            ],
            admin: $admin
        );

        $this->chatMessage = '';

        Notification::make()
            ->title('Message Sent & Delivered')
            ->body("Reply saved and synced to student chat record.")
            ->success()
            ->send();
    }

    public function insertChatSnippet(string $snippet): void
    {
        $this->chatMessage = $snippet;
    }

    public function applyPrebuiltTemplate(string $templateKey): void
    {
        $name = filled($this->recipientName) ? $this->recipientName : 'Student';
        $email = filled($this->recipientEmail) ? $this->recipientEmail : 'your registered email';

        $query = $this->selectedQueryId ? SupportQuery::find($this->selectedQueryId) : null;
        $phone = $query && $query->phone ? '+91 ' . preg_replace('/\D/', '', $query->phone) : 'your registered number';
        $inquiry = $query && $query->message ? $query->message : 'your inquiry';
        $category = $query && $query->category ? $query->category : 'Merchant Navy Preparation';

        switch ($templateKey) {
            case 'academic_doubt':
                $this->emailSubject = "Academic Resolution: {$category} - My Merchant Navy Faculty";
                $this->emailBody = "Dear {$name},\n\nThank you for reaching out to the My Merchant Navy Academic Support Desk.\n\nOur curriculum faculty has reviewed your doubt:\n\"{$inquiry}\"\n\nHere is the concept explanation and step-by-step resolution:\n1. Core Maritime Principle: All navigation, chartwork, and engineering formulas follow standard IMO / DG Shipping exam conventions.\n2. Step-by-Step Solution: Please review the updated practice ladder problems and formula sheets in your LMS course viewer.\n3. Detailed Video Solution: A comprehensive video breakdown of similar problems is available under your Mock Tests review panel.\n\nIf you need any further mathematical or conceptual clarification, please reply directly to this email or message us on WhatsApp.\n\nWarm regards,\nAcademic Support & Curriculum Faculty\nMy Merchant Navy\nhttps://lms.mymerchantnavy.com";
                break;

            case 'dns_sponsorship':
                $this->emailSubject = "Maritime Career Roadmap & Sponsorship Guidance: {$category}";
                $this->emailBody = "Dear {$name},\n\nWe have received your guidance request regarding shipping company sponsorships and IMU-CET admissions:\n\"{$inquiry}\"\n\nKey Strategic Recommendations from our Captain Mentors:\n1. Target Company Exams: Focus preparation on Anglo-Eastern, Synergy, Fleet Management, and Great Eastern entrance formats.\n2. Medical Standards: Ensure you fulfill DG Shipping medical fitness criteria (6/6 vision, normal color vision).\n3. Psychometric & Aptitude: Practice numerical ability and reasoning tests available in your Mock Tests section.\n\nOur senior maritime counseling mentor will be reaching out on your mobile number ({$phone}) for a 1-on-1 career briefing.\n\nBest of luck,\nAdmissions & Mentorship Team\nMy Merchant Navy";
                break;

            case 'batch_enrollment':
                $this->emailSubject = "LMS Batch Enrollment & Course Access Activated - My Merchant Navy";
                $this->emailBody = "Dear {$name},\n\nWelcome to My Merchant Navy! Your enrollment for {$category} has been verified and your student account is active.\n\nHow to Access Your Learning Portal:\n• Portal URL: https://lms.mymerchantnavy.com\n• Registered Email: {$email}\n• Unlocked Modules: Full syllabus video classes, chapterwise practice ladders, and all-India mock tests.\n\nStudy Plan Tip: Take the baseline diagnostic mock test first to assess your current score and target high-weightage chapters.\n\nWarm regards,\nStudent Onboarding Team\nMy Merchant Navy";
                break;

            case 'fee_payment':
                $this->emailSubject = "Course Fee Structure & Secure Enrollment Details - My Merchant Navy";
                $this->emailBody = "Dear {$name},\n\nThank you for your interest in enrolling in the {$category} comprehensive batch.\n\nFee & Enrollment Details:\n• Complete Course & Test Series Pass: Includes complete curriculum, mock test series, and formula notes.\n• Aspirant Discount: Applied automatically through our official enrollment link.\n• Secure Payment Gateway: Verified through Razorpay (UPI, Credit/Debit Card, Netbanking).\n• Official Enrollment Link: https://mymerchantnavy.com/pass/\n\nUpon successful payment, your LMS access is activated immediately. If you need installment assistance, let us know.\n\nWarm regards,\nAdmissions & Finance Desk\nMy Merchant Navy";
                break;

            case 'test_analysis':
                $this->emailSubject = "Mock Test Performance Analysis & Percentile Guidance - My Merchant Navy";
                $this->emailBody = "Dear {$name},\n\nOur academic evaluation team has reviewed your mock test performance on the My Merchant Navy LMS.\n\nPerformance Improvement Strategy:\n1. Negative Marking Control: In IMU-CET, negative marking has a significant impact on rank. Skip questions where confidence is below 70%.\n2. High-Yield Chapters: Focus extra time on Terrestrial Navigation, Vector Mathematics, and Mechanics.\n3. Detailed Solutions: Inspect incorrect answers in your 'Test Results' dashboard to identify common trap patterns.\n\nKeep practicing persistently. We are tracking your progress!\n\nAcademic Mentorship Team\nMy Merchant Navy";
                break;
        }

        Notification::make()
            ->title('Template Loaded')
            ->body('Prebuilt message populated into composer.')
            ->success()
            ->send();
    }

    public function sendPlatformEmail(): void
    {
        $recipient = trim($this->recipientEmail);
        $body = trim($this->emailBody);
        $subject = trim($this->emailSubject) ?: 'Response from My Merchant Navy Support';

        if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Notification::make()
                ->title('Invalid Recipient Email')
                ->body('Please enter a valid student email address in the To: field.')
                ->danger()
                ->send();
            return;
        }

        if (empty($body)) {
            Notification::make()->title('Please enter an email body before sending')->warning()->send();
            return;
        }

        $admin = Auth::guard('admin')->user();

        // If linked to an active support query, update it
        if ($this->selectedQueryId) {
            $query = SupportQuery::find($this->selectedQueryId);
            if ($query) {
                $query->update([
                    'admin_reply' => $body,
                    'status' => $this->emailStatus,
                    'replied_by_admin_id' => $admin?->id,
                    'replied_at' => now(),
                ]);
            }
        }

        try {
            Mail::raw($body, function ($msg) use ($recipient, $subject) {
                $msg->to($recipient)
                    ->subject($subject);
            });
        } catch (\Throwable $e) {
            report($e);
        }

        ActivityLog::record(
            event: 'support.email_dispatched',
            description: "Site Manager {$admin?->name} sent email to {$recipient}.",
            properties: [
                'recipient' => $recipient,
                'name' => $this->recipientName,
                'subject' => $subject,
                'query_id' => $this->selectedQueryId,
            ],
            admin: $admin
        );

        Notification::make()
            ->title('Email Sent Successfully')
            ->body("Dispatched via Brevo to {$recipient}.")
            ->success()
            ->send();
    }

    public function getQueriesProperty(): Collection
    {
        return SupportQuery::query()
            ->when($this->statusFilter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($this->statusFilter === 'resolved', fn ($q) => $q->where('status', 'resolved'))
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
