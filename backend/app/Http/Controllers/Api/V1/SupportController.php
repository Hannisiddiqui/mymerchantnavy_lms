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
            ],
        ]);
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

        $body = "A student submitted an inquiry via the LMS Chatbot:\n\n"
            ."Student Name: {$name}\n"
            ."Email Address: {$email}\n"
            ."Mobile / WhatsApp: {$phone}\n"
            ."Query Category: {$category}\n\n"
            ."Student Query / Doubt:\n"
            ."-------------------------------------\n"
            ."{$msg}\n"
            ."-------------------------------------\n\n"
            ."IP Address: ".$request->ip()."\n"
            ."Device: ".$request->userAgent()."\n"
            ."Timestamp: ".now()->format('Y-m-d H:i:s')." UTC\n\n"
            ."---\n"
            ."My Merchant Navy Student Support System";

        try {
            Mail::raw($body, function ($mail) use ($recipient, $name, $category) {
                $mail->to($recipient)
                    ->subject("[LMS Student Query] {$category} from {$name}");
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
