<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function loginForm() { return view('auth.login'); }
    public function registerForm() { return view('auth.register'); }
    public function forgotForm() { return view('auth.forgot-password'); }
    public function resetForm(Request $request, string $token) { return view('auth.reset-password', ['token' => $token, 'email' => $request->email]); }
    public function otpForm() { return view('auth.otp'); }

    public function sendResetLink(ForgotPasswordRequest $request)
    {
        $email = strtolower($request->validated('email'));
        $key = 'tradeflow-password-reset:'.$email.'|'.$request->ip();
        $lastSentAt = (int) $request->session()->get('password_reset_last_sent_at', 0);
        $remainingCooldown = max(0, 60 - (now()->timestamp - $lastSentAt));

        if ($remainingCooldown > 0) {
            return back()->withErrors(['email' => "A reset link was sent recently. Please wait {$remainingCooldown} seconds before requesting another link."])->onlyInput('email');
        }

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Too many reset requests. Please try again in {$seconds} seconds."])->onlyInput('email');
        }

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $exception) {
            // The broker stores the token before it sends the notification.
            // Do not leave an undelivered token behind when SMTP fails, as it
            // would throttle the next request as if an email had been sent.
            try {
                $user = User::query()->where('email', $email)->first();
                if ($user) {
                    Password::broker()->getRepository()->delete($user);
                }
            } catch (\Throwable $cleanupException) {
                Log::warning('TradeFlow could not remove an undelivered password reset token.', [
                    'email' => $email,
                    'exception' => $cleanupException->getMessage(),
                ]);
            }

            Log::error('TradeFlow password reset email could not be sent.', ['email' => $email, 'exception' => $exception->getMessage()]);
            return back()
                ->with('password_reset_failure_message', 'We could not send the reset email right now. Please try again shortly.')
                ->onlyInput('email');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            if ($status === Password::RESET_THROTTLED) {
                return back()->withErrors(['email' => 'A reset link was sent recently. Please wait 60 seconds before requesting another link.'])->onlyInput('email');
            }

            return back()->withErrors(['email' => __($status)])->onlyInput('email');
        }

        RateLimiter::hit($key, 600);
        $request->session()->put('password_reset_last_sent_at', now()->timestamp);
        $request->session()->put('password_reset_email', $email);

        return back()
            ->with('status', 'We emailed your password reset link.')
            ->withInput(['email' => $email]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->validated();
        $status = Password::reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->password_confirmation, 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            RateLimiter::clear('tradeflow-password-reset:'.strtolower($data['email']).'|'.$request->ip());
            $request->session()->forget(['password_reset_last_sent_at', 'password_reset_email']);

            return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in.');
        }

        return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (auth()->user()->status !== 'active') {
                Auth::logout();
                return back()->withErrors(['email' => 'Your account is inactive. Please contact your business owner.'])->onlyInput('email');
            }

            $request->session()->regenerate();
            $request->session()->forget('url.intended');
            auth()->user()->forceFill(['last_login_at' => now()])->save();
            if (auth()->user()->business_id) {
                AuditLog::create([
                    'business_id' => auth()->user()->business_id,
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name,
                    'role' => auth()->user()->role,
                    'module' => 'Authentication',
                    'action' => 'login',
                    'description' => auth()->user()->name.' signed in.',
                    'route' => 'login.store',
                    'occurred_at' => now(),
                ]);
            }
            Log::info('TradeFlow staff login permissions loaded', [
                'user_id' => auth()->id(),
                'role' => auth()->user()->role,
                'business_id' => auth()->user()->business_id,
                'permissions' => auth()->user()->permissions ?? [],
            ]);

            return redirect()->route($this->dashboardRoute(auth()->user()));
        }

        return back()->withErrors(['email' => 'Invalid login details.'])->onlyInput('email');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['nullable', Rule::in(['retailer', 'business_owner'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'retailer',
            'status' => 'active',
        ]);

        Auth::login($user);

        return redirect()->route($this->dashboardRoute($user));
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user?->business_id) {
            AuditLog::create([
                'business_id' => $user->business_id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role,
                'module' => 'Authentication',
                'action' => 'logout',
                'description' => $user->name.' signed out.',
                'route' => 'logout',
                'occurred_at' => now(),
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function dashboardRoute(User $user): string
    {
        return match ($user->role) {
            'super_admin' => 'admin.dashboard',
            'retailer' => 'retailer.dashboard',
            'custom_staff' => 'staff.dashboard',
            default => 'business.dashboard',
        };
    }
}
