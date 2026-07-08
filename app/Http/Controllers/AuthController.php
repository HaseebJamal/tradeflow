<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function loginForm() { return view('auth.login'); }
    public function registerForm() { return view('auth.register'); }
    public function forgotForm() { return view('auth.forgot-password'); }
    public function otpForm() { return view('auth.otp'); }

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
            auth()->user()->forceFill(['last_login_at' => now()])->save();
            Log::info('TradeFlow staff login permissions loaded', [
                'user_id' => auth()->id(),
                'role' => auth()->user()->role,
                'business_id' => auth()->user()->business_id,
                'permissions' => auth()->user()->permissions ?? [],
            ]);

            return redirect()->intended(route('dashboard.redirect'));
        }

        return back()->withErrors(['email' => 'Invalid login details.'])->onlyInput('email');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', 'min:8'],
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

        return redirect()->route('dashboard.redirect');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
