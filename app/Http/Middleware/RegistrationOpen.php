<?php

namespace App\Http\Middleware;

use App\Services\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationOpen
{
    public function __construct(
        protected SettingsRepository $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $open = $this->settings->bool('registration_open', false);

        if ($open) {
            return $next($request);
        }

        if ($request->isMethod('POST')) {
            abort(403, 'Регистрация временно приостановлена.');
        }

        return response()->view('auth.registration-closed', [], 200);
    }
}
