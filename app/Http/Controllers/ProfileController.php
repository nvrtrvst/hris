<?php

namespace App\Http\Controllers;

use App\Http\Requests\PegawaiSelfUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route(auth('web_mobile')->check() ? 'presensi.profile.edit' : 'profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function editPegawai(Request $request): Response
    {
        $user = $request->user();
        $pegawai = $user?->pegawai;

        return Inertia::render($request->is('mobile*') ? 'Mobile/LengkapiData' : 'Profile/LengkapiData', [
            'pegawai' => $pegawai,
        ]);
    }

    public function updatePegawai(PegawaiSelfUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $pegawai = $user?->pegawai;

        if (! $pegawai) {
            return redirect()->back()->with('error', 'Data pegawai tidak ditemukan.');
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($pegawai->foto) {
                Storage::disk('public')->delete($pegawai->foto);
            }
            $data['foto'] = $request->file('foto')->store('foto-pegawai', 'public');
        }

        $pegawai->update($data);

        $isMobile = $request->is('mobile*') || auth('web_mobile')->check();

        return Redirect::route($isMobile ? 'presensi.dashboard' : 'dashboard')
            ->with('message', 'Data berhasil disimpan.');
    }
}
