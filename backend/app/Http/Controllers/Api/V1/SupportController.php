<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\SupportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    /** Return public support configuration (WhatsApp number & email). */
    public function config(): JsonResponse
    {
        return response()->json([
            'data' => [
                'support_email' => Setting::get('support_email', 'support.mymerchantnavy@gmail.com'),
                'support_whatsapp_number' => Setting::get('support_whatsapp_number', '919911223344'),
                'page_visibility' => $this->getPageVisibilitySettings(),
            ],
        ]);
    }

    /** Return student LMS page visibility settings. */
    public function pageVisibility(): JsonResponse
    {
        return response()->json([
            'data' => $this->getPageVisibilitySettings(),
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    private function getPageVisibilitySettings(): array
    {
        return [
            'my_courses' => filter_var(Setting::get('page_visibility_my_courses', '1'), FILTER_VALIDATE_BOOLEAN),
            'batches' => filter_var(Setting::get('page_visibility_batches', '1'), FILTER_VALIDATE_BOOLEAN),
            'mock_tests' => filter_var(Setting::get('page_visibility_mock_tests', '1'), FILTER_VALIDATE_BOOLEAN),
            'performance' => filter_var(Setting::get('page_visibility_performance', '1'), FILTER_VALIDATE_BOOLEAN),
            'results' => filter_var(Setting::get('page_visibility_results', '1'), FILTER_VALIDATE_BOOLEAN),
            'notes' => filter_var(Setting::get('page_visibility_notes', '1'), FILTER_VALIDATE_BOOLEAN),
            'profile' => filter_var(Setting::get('page_visibility_profile', '1'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** Return history of student's queries and manager responses for the in-app chatbot. */
    public function myQueries(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $email = $user?->email ?: $request->query('email');

        if (! $email) {
            return response()->json(['data' => []]);
        }

        $queries = SupportQuery::where('email', $email)
            ->orWhere('student_id', $user?->id)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return response()->json(['data' => $queries]);
    }

    /** Handle queries submitted via student chatbot. */
    public function sendQuery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
        ]);

        $user = $request->user('sanctum');
        $recipient = Setting::get('support_email', 'support.mymerchantnavy@gmail.com');
        $category = $data['category'] ?: 'General Inquiry';
        $channel = $data['channel'] ?: 'web_chat';
        $name = $data['name'];
        $email = $data['email'];
        $phone = $data['phone'] ?: 'Not provided';
        $msg = $data['message'];
        $cleanPhone = preg_replace('/\D/', '', (string) $phone);

        // Store query in database for Site Manager and Admin Inbox
        $queryRecord = SupportQuery::create([
            'student_id' => $user?->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== 'Not provided' ? $phone : null,
            'category' => $category,
            'channel' => $channel,
            'message' => $msg,
            'status' => 'open',
            'ip_address' => $request->ip(),
        ]);

        $viewData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'cleanPhone' => $cleanPhone,
            'category' => $category,
            'msg' => $msg,
            'ip' => $request->ip(),
            'device' => $request->userAgent(),
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        // Sanitize recipient email(s)
        $emails = array_filter(array_map('trim', preg_split('/[,\s;]+/', (string) $recipient)));
        $validEmails = array_values(array_filter($emails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if (empty($validEmails)) {
            $validEmails = ['support.mymerchantnavy@gmail.com'];
        }

        try {
            Mail::send('emails.student-support-query', $viewData, function ($mail) use ($validEmails, $name, $category, $email) {
                $mail->to($validEmails)
                    ->replyTo($email, $name)
                    ->subject("[MMN Student Query] {$category} from {$name}");
            });
        } catch (\Throwable $e) {
            report($e);
        }

        ActivityLog::record(
            event: 'student.support_query',
            description: "Student {$name} ({$email}) submitted {$category} via in-app chatbot.",
            properties: [
                'query_id' => $queryRecord->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'category' => $category,
                'channel' => $channel,
                'message' => $msg,
            ]
        );

        return response()->json([
            'ok' => true,
            'query_id' => $queryRecord->id,
            'message' => 'Thank you! Your query has been received by our site support team. We will review and reply directly to your chat and email.',
        ]);
    }
}

