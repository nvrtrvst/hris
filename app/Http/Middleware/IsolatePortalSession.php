<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class IsolatePortalSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Deteksi portal via subdomain (production) dengan fallback path (local dev).
        $mobileDomain = config('domains.mobile');
        $isMobile = $request->getHost() === $mobileDomain;
        if (! $isMobile && ($request->is('mobile') || $request->is('mobile/*'))) {
            $isMobile = true;
        }

        // /sanctum/csrf-cookie (Sanctum, root path) tidak punya prefix mobile.
        // Di dev (host sama), pakai Referer/Origin untuk menentukan portal agar
        // token CSRF dienkripsi di session portal yang benar (bukan admin).
        if (! $isMobile && $request->is('sanctum/csrf-cookie')) {
            $referer = $request->header('Referer') ?: $request->header('Origin');
            if ($referer && (str_contains($referer, '/mobile') || str_contains($referer, (string) $mobileDomain))) {
                $isMobile = true;
            }
        }

        // /presensi/photo/* dipakai lintas portal (admin + mobile). Di dev (host
        // sama), tentukan portal dari cookie session yang BENAR-BENAR terkirim di
        // request — bukan tebak dari path. Fallback ke Referer bila kedua cookie
        // hadir sekaligus (user login di dua portal) atau tidak ada sama sekali.
        if (! $isMobile && $request->is('presensi/photo/*')) {
            $hasMobile = $request->cookies->has('hris_mobile_session');
            $hasAdmin = $request->cookies->has('hris_mgmt_session');

            if ($hasMobile && ! $hasAdmin) {
                $isMobile = true;
            } elseif ($hasAdmin && ! $hasMobile) {
                $isMobile = false;
            } else {
                $referer = $request->header('Referer') ?: $request->header('Origin');
                if ($referer && (str_contains($referer, '/mobile') || str_contains($referer, (string) $mobileDomain))) {
                    $isMobile = true;
                }
            }
        }

        if ($isMobile) {
            Config::set('session.cookie', 'hris_mobile_session');
            Config::set('session.path', '/');
            Config::set('session.lifetime', 1440);
            Config::set('auth.defaults.guard', 'web_mobile');

        } else {
            Config::set('session.cookie', 'hris_mgmt_session');
            Config::set('session.path', '/');
            Config::set('session.lifetime', 120);
            Config::set('auth.defaults.guard', 'web_admin');

        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');

        return $response;
    }
}
