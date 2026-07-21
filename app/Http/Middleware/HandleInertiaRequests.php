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
     */
    public function version(Request $request): ?string
    {
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
            $relations = ['pegawai.pengajuanIzins'];
            if ($request->getHost() === config('domains.mobile') || $request->is('mobile') || $request->is('mobile/*')) {
                $relations['pegawai.units'] = fn ($query) => $query->select('unit_sekolah.id', 'nama', 'singkatan', 'logo');
            }
            $user->load($relations);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'roles' => $user ? $user->roles->pluck('name') : [],
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
