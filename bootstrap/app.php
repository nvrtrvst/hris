<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsolatePortalSession;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('presensi:finalize-alpa')->dailyAt('01:00')->withoutOverlapping();
        $schedule->command('presensi:cleanup-foto')->dailyAt('01:30')->withoutOverlapping();
        // Reminder presensi pagi (06:00) & sebelum jam mulai (10:00) utk yang belum absen
        $schedule->command('presensi:reminder')->dailyAt('06:00')->withoutOverlapping();
        $schedule->command('presensi:reminder')->dailyAt('10:00')->withoutOverlapping();
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

        $middleware->validateCsrfTokens(except: [
        ]);

        $middleware->alias([
            'isolate.portal' => IsolatePortalSession::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->getHost() === config('domains.mobile') || $request->is('mobile') || $request->is('mobile/*')) {
                return route('presensi.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->getHost() === config('domains.mobile') || $request->is('mobile') || $request->is('mobile/*')) {
                return route('presensi.dashboard');
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        if (config('sentry.dsn')) {
            $exceptions->report(fn (Throwable $e) => Integration::report($e));
        }
    })->create();
