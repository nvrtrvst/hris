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

        return redirect()->intended(route('mobile.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web_mobile')->logout();

        // $request->session()->invalidate();
        // $request->session()->regenerateToken();

        return redirect('/mobile/login');
    }
}
