<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePegawaiComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $pegawai = $user?->pegawai;

        if ($pegawai && ! $pegawai->isDataComplete()) {
            $route = $request->route()?->getName();

            $allowed = [
                'lengkapi-data', 'lengkapi-data.store',
                'presensi.lengkapi-data', 'presensi.lengkapi-data.store',
                'profile.edit', 'presensi.profile.edit',
                'logout', 'presensi.logout',
            ];

            if (! in_array($route, $allowed)) {
                $isMobile = $request->is('mobile*') || $request->getHost() === env('MOBILE_DOMAIN');

                return redirect()->route($isMobile ? 'presensi.lengkapi-data' : 'lengkapi-data');
            }
        }

        return $next($request);
    }
}
