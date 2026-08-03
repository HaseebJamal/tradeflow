<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyOnboardingAccessService
{
    private const SESSION_KEY = 'admin.company_onboarding_access';

    public function remember(Request $request, Business $company, User $owner, string $temporaryPassword): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'company_id' => $company->id,
            'owner_id' => $owner->id,
            'admin_id' => $request->user()?->id,
            'temporary_password' => $temporaryPassword,
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
        ]);
    }

    public function context(Request $request, Business $company): ?array
    {
        $stored = $request->session()->get(self::SESSION_KEY);

        if (! is_array($stored)
            || ($stored['company_id'] ?? null) !== $company->id
            || ($stored['admin_id'] ?? null) !== $request->user()?->id
            || ($stored['expires_at'] ?? 0) < now()->getTimestamp()) {
            $this->forget($request);

            return null;
        }

        $owner = $company->relationLoaded('owner') ? $company->owner : $company->owner()->first();
        if (! $owner || $owner->id !== ($stored['owner_id'] ?? null)) {
            $this->forget($request);

            return null;
        }

        return [
            'company_name' => $company->business_name,
            'owner_name' => $owner->name,
            'owner_email' => $owner->email,
            'owner_phone' => $owner->phone,
            'login_email' => $owner->email,
            'temporary_password' => $stored['temporary_password'],
            'login_url' => route('login'),
            'platform_name' => app(PlatformSettingsService::class)->name(),
            'whatsapp_digits' => app(PhoneNumberService::class)->whatsappDigits($owner->phone),
        ];
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function emailSubject(array $context): string
    {
        return 'Your '.$context['platform_name'].' business account is ready';
    }

    public function emailMessage(array $context): string
    {
        return $this->message($context, 'has been created successfully.');
    }

    public function whatsAppMessage(array $context): string
    {
        return $this->message($context, 'has been created.');
    }

    public function copyMessage(array $context): string
    {
        return $this->emailMessage($context);
    }

    private function message(array $context, string $creationText): string
    {
        return "Hello {$context['owner_name']},\n\n"
            ."Your {$context['platform_name']} business account for {$context['company_name']} {$creationText}\n\n"
            ."Login Email: {$context['login_email']}\n"
            ."Temporary Password: {$context['temporary_password']}\n"
            ."\n"
            ."Please sign in and change your password after your first login.\n\n"
            ."Regards,\n{$context['platform_name']}";
    }
}
