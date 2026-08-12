<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * Asset version = mtime manifest Vite. Berubah setiap `npm run build`,
     * sehingga Inertia client mendeteksi mismatch version dan otomatis
     * melakukan full-page reload — mencegah error stale-bundle setelah
     * deploy (tanpa hard refresh manual).
     */
    public function version(Request $request): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (is_file($manifest)) {
            $mtime = @filemtime($manifest);

            if ($mtime !== false) {
                return (string) $mtime;
            }
        }

        // Mode dev (`npm run dev`) tanpa manifest: nonaktifkan version check.
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $relations = ['pegawai'];
            if ($request->getHost() === config('domains.mobile') || $request->is('mobile') || $request->is('mobile/*')) {
                $relations['pegawai.units'] = fn ($query) => $query->select('unit_sekolah.id', 'nama', 'singkatan', 'logo');
            }
            $user->load($relations);
        }

        $pegawai = $user?->pegawai;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'roles' => $user ? $user->roles->pluck('name') : [],
                'is_approver' => $user ? $user->isApprover() : false,
                'pegawai_complete' => $pegawai ? $pegawai->isDataComplete() : true,
                'unread_notifications' => $user ? $user->unreadNotifications()->count() : 0,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
                'generated_password' => fn () => $request->session()->get('generated_password'),
            ],
        ];
    }
}
