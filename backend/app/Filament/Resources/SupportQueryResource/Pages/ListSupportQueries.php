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
use Illuminate\Support\Facades\RateLimiter;

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

    /** Max replies / emails one admin can send per hour (stops a stolen session from being used to spam students). */
    private const SENDS_PER_HOUR = 40;

    private function allowedToSend(): bool
    {
        $key = 'support-send:'.(Auth::guard('admin')->id() ?? 'x');
        if (RateLimiter::tooManyAttempts($key, self::SENDS_PER_HOUR)) {
            Notification::make()->title('Sending limit reached')->body('Too many messages in the last hour. Please wait a little and try again.')->danger()->send();

            return false;
        }
        RateLimiter::hit($key, 3600);

        return true;
    }

    /**
     * Saves the reply to the student's record and e-mails it to them. WhatsApp itself is not sent from here
     * (that needs the WhatsApp Business API): the "Open in WhatsApp" button opens the message ready to send.
     */
    public function sendReply(): void
    {
        $msg = trim($this->chatMessage);
        $query = $this->selectedQueryId ? SupportQuery::find($this->selectedQueryId) : null;
        if (! $query || $msg === '') {
            return;
        }
        if (! $this->allowedToSend()) {
            return;
        }

        $admin = Auth::guard('admin')->user();
        $emailed = false;
        $problem = null;

        if (filter_var($query->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::send('emails.support-reply', [
                    'studentName' => $query->name,
                    'category' => $query->category,
                    'originalMessage' => $query->message,
                    'replyMessage' => $msg,
                    'facultyName' => $admin?->name ?? 'Academic Support Team',
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                ], fn ($m) => $m->to($query->email)->subject("[My Merchant Navy] Response to your inquiry: {$query->category}"));
                $emailed = true;
            } catch (\Throwable $e) {
                report($e);
                $problem = 'The email could not be sent (check the mail settings).';
            }
        } else {
            $problem = 'This student has no valid email address.';
        }

        // The reply is always kept, so nothing typed is lost; the query is only marked resolved when the student got it.
        $query->update([
            'admin_reply' => filled($query->admin_reply) ? $query->admin_reply."\n\n".$msg : $msg,
            'status' => $emailed ? 'resolved' : $query->status,
            'replied_by_admin_id' => $admin?->id,
            'replied_at' => now(),
        ]);

        ActivityLog::record(
            event: 'support.reply_sent',
            description: "{$admin?->name} replied to {$query->name} ({$query->email}); emailed: ".($emailed ? 'yes' : 'no').'.',
            properties: ['query_id' => $query->id, 'emailed' => $emailed],
            admin: $admin
        );

        $this->chatMessage = '';

        $emailed
            ? Notification::make()->title('Reply saved and emailed')->body("Sent to {$query->email}. Use “Open in WhatsApp” to message them there too.")->success()->send()
            : Notification::make()->title('Reply saved, but not emailed')->body($problem.' Use “Open in WhatsApp” to reach the student.')->warning()->send();
    }

    public function sendChatMessage(): void
    {
        $this->sendReply();
    }

    public function setQueryStatus(int $id, string $status): void
    {
        if (! in_array($status, ['open', 'resolved'], true)) {
            return;
        }
        $query = SupportQuery::find($id);
        if ($query) {
            $query->update(['status' => $status]);
            ActivityLog::record(
                event: 'support.status_changed',
                description: 'Support query #'.$query->id." marked {$status}.",
                properties: ['query_id' => $query->id, 'status' => $status],
                admin: Auth::guard('admin')->user()
            );
        }
    }

    /** wa.me link that opens WhatsApp with the typed message ready to send (the admin presses send there). */
    public function getWhatsappUrlProperty(): ?string
    {
        $q = $this->selectedQuery;
        $digits = preg_replace('/\D/', '', (string) $q?->phone);
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }
        if (strlen($digits) < 11) {
            return null;
        }
        $text = trim($this->chatMessage) ?: "Hello {$q->name}, this is My Merchant Navy regarding your inquiry ({$q->category}). How can we help?";

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
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
        $subject = trim(str_replace(["\r", "\n"], ' ', $this->emailSubject)) ?: 'Response from My Merchant Navy Support';

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

        if (! $this->allowedToSend()) {
            return;
        }

        try {
            Mail::raw($body, function ($msg) use ($recipient, $subject) {
                $msg->to($recipient)->subject($subject);
            });
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('The email was not sent')->body('The mail server refused it. Nothing was marked as replied. Please check the mail settings and try again.')->danger()->send();

            return;
        }

        // Only after it really went out: link it to the support query.
        if ($this->selectedQueryId && ($query = SupportQuery::find($this->selectedQueryId))) {
            $query->update([
                'admin_reply' => $body,
                'status' => $this->emailStatus,
                'replied_by_admin_id' => $admin?->id,
                'replied_at' => now(),
            ]);
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
            ->body("Delivered to the mail server for {$recipient}.")
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
