<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber?->status === 'Active') {
            $payload = [
                'alreadySubscribed' => true,
                'message' => "You're already subscribed.",
            ];

            return $request->expectsJson()
                ? response()->json($payload)
                : back()->with('newsletter_feedback', $payload);
        }

        if ($subscriber) {
            $subscriber->update(['status' => 'Active', 'subscribed_at' => now()]);
        } else {
            NewsletterSubscriber::create(['email' => $email, 'status' => 'Active', 'subscribed_at' => now()]);
        }

        $payload = [
            'message' => 'Thanks for subscribing to Profit Point updates.',
        ];

        return $request->expectsJson()
            ? response()->json($payload, 201)
            : back()->with('newsletter_feedback', $payload);
    }
}
