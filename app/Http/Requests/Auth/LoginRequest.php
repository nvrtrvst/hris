<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(string $guard = 'web_admin'): void
    {
        $this->ensureIsNotRateLimited($guard);

        if (! Auth::guard($guard)->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($guard));

            // Kita juga bisa memberikan pesan custom sesuai PRD jika diperlukan
            // Tapi untuk sekarang kita tetap lempar exception auth failed.
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang Anda masukkan tidak sesuai. Periksa kembali atau hubungi HR Pusat jika lupa kata sandi.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($guard));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(string $guard = 'web_admin'): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($guard), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey($guard));

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam " . ceil($seconds / 60) . " menit.",
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(string $guard = 'web_admin'): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$guard.'|'.$this->ip());
    }
}
