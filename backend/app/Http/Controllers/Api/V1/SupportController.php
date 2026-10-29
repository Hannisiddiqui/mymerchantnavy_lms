<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
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
        ]);
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

    /** Handle queries submitted via student chatbot. */
    public function sendQuery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
        ]);

        $recipient = Setting::get('support_email', 'support.mymerchantnavy@gmail.com');
        $category = $data['category'] ?: 'General Inquiry';
        $name = $data['name'];
        $email = $data['email'];
        $phone = $data['phone'] ?: 'Not provided';
        $msg = $data['message'];
        $cleanPhone = preg_replace('/\D/', '', (string) $phone);

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

        try {
            Mail::send('emails.student-support-query', $viewData, function ($mail) use ($recipient, $name, $category, $email) {
                $mail->to($recipient)
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
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'category' => $category,
                'message' => $msg,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Thank you! Your query has been delivered to our academic faculty. We will reply to your email shortly.',
        ]);
    }
}

