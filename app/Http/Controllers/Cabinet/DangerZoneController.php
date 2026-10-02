<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestAccountDeletionRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DangerZoneController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $service,
    ) {}

    /**
     * Handle the user-initiated account deletion request (frontend-spec §6.12, step 1).
     *
     * Idempotent: if the account is already in deletion_requested state,
     * returns to the home page with an informational flash message.
     * On success: logs the user out of the current session and redirects home.
     */
    public function request(RequestAccountDeletionRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isDeletionRequested()) {
            return redirect()
                ->route('home')
                ->with('status', 'Запрос на удаление уже отправлен.');
        }

        $this->service->request($user, (string) $request->validated('reason'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('status', 'Запрос на удаление отправлен.');
    }
}
