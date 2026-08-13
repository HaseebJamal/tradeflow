<?php

namespace App\Http\Controllers;

use App\Mail\PublicContactInquiryMail;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $settings = app(PlatformSettingsService::class)->current();
        $supportEmail = trim((string) $settings->support_email);
        if (! filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Public contact email was not sent because Platform Support Email is not configured.', [
                'visitor_email' => strtolower(trim($data['email'])),
            ]);

            return back()->withInput()->withErrors(['contact' => 'We could not send your message right now. Please try again.']);
        }

        $fingerprint = 'public-contact:'.hash('sha256', strtolower($data['email']).'|'.$data['phone'].'|'.trim($data['message']));
        if (! Cache::add($fingerprint, true, now()->addMinutes(2))) {
            return back()->withInput()->withErrors(['message' => 'This message was recently sent. Please wait before sending it again.']);
        }

        $inquiry = [
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => strtolower(trim($data['email'])),
            'message' => trim($data['message']),
            'submitted_at' => now(config('app.timezone'))->format('n/j/Y, g:i A'),
        ];

        try {
            Mail::to($supportEmail)->send(new PublicContactInquiryMail(
                $inquiry,
                $settings->company_name ?: app(PlatformSettingsService::class)->name(),
            ));
        } catch (\Throwable $exception) {
            // Release the duplicate guard after a failed transport so a
            // visitor can retry, while keeping the actual mail error private.
            Cache::forget($fingerprint);
            Log::error('Public contact email delivery failed.', [
                'visitor_email' => $inquiry['email'],
                'support_email' => $supportEmail,
                'exception' => $exception,
            ]);

            return back()->withInput()->withErrors(['contact' => "We couldn't send your message right now. Please try again."]);
        }

        return back()->with('success', 'Message sent successfully.');
    }
}
