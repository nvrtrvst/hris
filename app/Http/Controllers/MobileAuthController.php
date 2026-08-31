<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MobileAuthController extends Controller
{
    public function create()
    {
        return Inertia::render('Mobile/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate('web_mobile');

        $request->session()->regenerate();

        $user = Auth::guard('web_mobile')->user();
        if ($user && $user->needsPasswordChange()) {
            return redirect(route('presensi.profile.edit', [], false))
                ->with('message', 'Anda harus mengganti password sebelum melanjutkan.');
        }

        return redirect()->intended(route('presensi.dashboard', [], false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web_mobile')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('presensi.login');
    }
}
