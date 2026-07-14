<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IsolatePortalSession
{
    private function expireCookie(Response $response, string $name, string $path = '/'): void
    {
        $response->headers->clearCookie($name, path: $path);
        if ($path !== '/') {
            $response->headers->clearCookie($name, path: '/');
        }
    }

    /**
     * Format Laravel SessionGuard::getRecallerName():
     *   'remember_'.$this->name.'_'.sha1(static::class)
     * https://github.com/laravel/framework/blob/11.x/src/Illuminate/Auth/SessionGuard.php
     */
    private function getRecallerName(string $guard): string
    {
        return 'remember_'.$guard.'_'.sha1('Illuminate\Auth\SessionGuard');
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $defaultCookie = Str::slug((string) config('app.name', 'laravel')).'-session';
        $adminRemember = $this->getRecallerName('web_admin');
        $mobileRemember = $this->getRecallerName('web_mobile');

        // Deteksi portal via subdomain (production) dengan fallback path (local dev).
        $mobileDomain = config('domains.mobile');
        $isMobile = $request->getHost() === $mobileDomain;
        if (!$isMobile && ($request->is('mobile') || $request->is('mobile/*'))) {
            $isMobile = true;
        }

        $expireCookies = [];

        if ($isMobile) {
            Config::set('session.cookie', 'hris_mobile_session');
            Config::set('session.path', '/');
            Config::set('session.lifetime', 1440);
            Config::set('auth.defaults.guard', 'web_mobile');

            $request->cookies->remove('hris_mgmt_session');
            $request->cookies->remove($defaultCookie);
            $expireCookies[] = ['hris_mgmt_session', '/'];

            foreach ($request->cookies->all() as $name => $value) {
                if ($name === $adminRemember) {
                    $request->cookies->remove($name);
                    $expireCookies[] = [$name, '/'];
                }
            }
        } else {
            Config::set('session.cookie', 'hris_mgmt_session');
            Config::set('session.path', '/');
            Config::set('session.lifetime', 120);
            Config::set('auth.defaults.guard', 'web_admin');

            $request->cookies->remove('hris_mobile_session');
            $request->cookies->remove($defaultCookie);
            $expireCookies[] = ['hris_mobile_session', '/'];

            foreach ($request->cookies->all() as $name => $value) {
                if ($name === $mobileRemember) {
                    $request->cookies->remove($name);
                    $expireCookies[] = [$name, '/'];
                }
            }
        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');

        foreach ($expireCookies as [$name, $path]) {
            $this->expireCookie($response, $name, $path);
        }

        return $response;
    }
}
