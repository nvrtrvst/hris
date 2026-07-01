<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk membatasi akses berdasarkan role user.
 * 
 * Contoh penggunaan di route:
 *   Route::middleware('role:superadmin,admin_unit')->group(...)
 *   Route::middleware('role:superadmin')->get(...)
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$roles Role yang diizinkan (dipisahkan koma)
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Akses ditolak. Anda belum login.');
        }

        if (!in_array($user->role, $roles)) {
            abort(403, 'Akses ditolak. Role Anda (' . $user->role . ') tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
