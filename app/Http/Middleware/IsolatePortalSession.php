<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;

class IsolatePortalSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('mobile') || $request->is('mobile/*')) {
            Config::set('session.cookie', 'hris_mobile_session');
            Config::set('session.path', '/mobile');
            Config::set('session.lifetime', 1440); // 24 hours
            Config::set('auth.defaults.guard', 'web_mobile');
        } else {
            Config::set('session.cookie', 'hris_mgmt_session');
            Config::set('session.path', '/');
            Config::set('session.lifetime', 120); // 2 hours
            Config::set('auth.defaults.guard', 'web_admin');
        }

        return $next($request);
    }
}
