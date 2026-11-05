<?php

use App\Http\Middleware\IdentifyGuest;
use App\Http\Middleware\RegistrationOpen;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\SectionAccess;
use App\Http\Middleware\SetLocale;
use App\Jobs\DispatchEventRemindersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new DispatchEventRemindersJob)
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('app:cleanup-activities')
            ->dailyAt('03:00')
            ->withoutOverlapping();

        // Daily DB-only backup and its retention cleanup (ADR-004 R7,
        // contract section 10). Full backups stay manual / pre-deploy.
        $schedule->command('app:backup --mode=db --triggered-by=cron')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        $schedule->command('app:backup-cleanup')
            ->dailyAt('04:30')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // The cookie banner acknowledgement and the localization cookies are
        // written as plain (unencrypted) values: the banner flag by JavaScript,
        // `locale` by the server and `tz` by JavaScript. They must be excluded
        // from cookie encryption — otherwise EncryptCookies fails to decrypt
        // them and drops the value (ADR-011 §9 L2).
        $middleware->encryptCookies(except: [
            'cookie_banner_ack',
            'locale',
            'tz',
        ]);

        // SetLocale runs after IdentifyGuest: the session is already started and
        // the resolved locale can rely on session, cookie and Accept-Language.
        $middleware->web(append: [
            IdentifyGuest::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'section.access' => SectionAccess::class,
            'registration.open' => RegistrationOpen::class,
            'mfa.required' => RequireMfa::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
