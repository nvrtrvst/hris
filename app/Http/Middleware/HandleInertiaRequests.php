<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
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
            // Pegawai selalu dimuat (badge/pegawai_complete), unit & jabatan primer
            // dibutuhkan halaman profil web & mobile.
            $relations = [
                'pegawai',
                'pegawai.units' => fn ($query) => $query->select('unit_sekolah.id', 'nama', 'singkatan', 'logo'),
                'pegawai.jabatans' => fn ($query) => $query->select('jabatan.id', 'nama'),
            ];
            $user->load($relations);
        }

        $pegawai = $user?->pegawai;

        // Jumlah pengumuman BELUM DIBACA untuk pegawai (badge ikon megaphone di mobile).
        // Dihitung hanya saat user punya relasi pegawai — admin (tanpa pegawai) = 0.
        $announcementCount = 0;
        if ($pegawai) {
            $unitId = $pegawai->units->first()?->id;
            $announcementCount = Announcement::published()->forUnit($unitId)->unreadFor($pegawai)->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'roles' => $user ? $user->roles->pluck('name') : [],
                'is_approver' => $user ? $user->isApprover() : false,
                'is_payroll_operator' => $user ? $user->isPayrollOperator() : false,
                'pegawai_complete' => $pegawai ? $pegawai->isDataComplete() : true,
                'unread_notifications' => $user ? $user->unreadNotifications()->count() : 0,
                'announcement_count' => $announcementCount,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
                'generated_password' => fn () => $request->session()->get('generated_password'),
            ],
        ];
    }
}
