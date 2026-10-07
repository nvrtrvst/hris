<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsolatePortalSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withSchedule(function (Schedule $schedule) {

        $schedule->command('reminders:process')
            ->everyFiveMinutes()
            ->withoutOverlapping();

    })

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->append([
            IsolatePortalSession::class,
        ]);

        $middleware->web(
            append: [
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ]
        );

        $middleware->validateCsrfTokens(except: []);

        $middleware->alias([
            'isolate.portal' => IsolatePortalSession::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {

            if (
                $request->getHost() === config('domains.mobile') ||
                $request->is('mobile') ||
                $request->is('mobile/*')
            ) {
                return route('presensi.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {

            if (
                $request->getHost() === config('domains.mobile') ||
                $request->is('mobile') ||
                $request->is('mobile/*')
            ) {
                return route('presensi.dashboard');
            }

            return route('dashboard');
        });

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | JSON Response
        |--------------------------------------------------------------------------
        */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Deteksi portal (pola sama EnsurePegawaiComplete): portal mobile tidak
        // punya route /dashboard, jadi CTA halaman error harus beda per portal.
        $portalOf = fn (Request $request): string => ($request->getHost() === config('domains.mobile') || $request->is('mobile') || $request->is('mobile/*')) ? 'mobile' : 'admin';

        // Pesan kustom dari abort(403, '...') untuk ditampilkan di halaman error.
        // abort(403) tanpa pesan → null (pakai teks default UI).
        $abortMessage = fn (HttpException $e): ?string => $e->getMessage() !== '' ? $e->getMessage() : null;

        /*
        |--------------------------------------------------------------------------
        | Custom 403 - AuthorizationException
        |--------------------------------------------------------------------------
        |
        | Menangani:
        | - authorize()
        | - Gate::authorize()
        | - Policy authorization
        |
        */

        $exceptions->render(function (
            AuthorizationException $e,
            Request $request
        ) use ($portalOf) {
            return Inertia::render('Errors/403', ['portal' => $portalOf($request)])
                ->toResponse($request)
                ->setStatusCode(403);
        });

        /*
        |--------------------------------------------------------------------------
        | Custom 403 - abort(403)
        |--------------------------------------------------------------------------
        |
        | Menangani:
        | - abort(403)
        | - abort(403, 'Akses ditolak.')
        | - abort_unless(..., 403)
        |
        */

        $exceptions->render(function (
            HttpException $e,
            Request $request
        ) use ($portalOf, $abortMessage) {
            if ($e->getStatusCode() === 403) {
                return Inertia::render('Errors/403', [
                    'portal' => $portalOf($request),
                    'message' => $abortMessage($e),
                ])
                    ->toResponse($request)
                    ->setStatusCode(403);
            }

            return null;
        });

        /*
        |--------------------------------------------------------------------------
        | Custom 500
        |--------------------------------------------------------------------------
        |
        | Menangani error server / exception yang menghasilkan HTTP 500.
        |
        */

        $exceptions->respond(function ($response) use ($portalOf) {

            if ($response->getStatusCode() === 500) {
                return Inertia::render('Errors/500', ['portal' => $portalOf(request())])
                    ->toResponse(request())
                    ->setStatusCode(500);
            }

            return $response;
        });

        /*
        |--------------------------------------------------------------------------
        | Sentry
        |--------------------------------------------------------------------------
        */

        if (config('sentry.dsn')) {
            $exceptions->report(function (Throwable $e) {
                Integration::captureUnhandledException($e);
            });
        }

    })

    ->create();
