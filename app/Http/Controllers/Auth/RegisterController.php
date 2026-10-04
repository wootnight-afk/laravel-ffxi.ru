<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Events\UserRegisteredForActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\NicknameSuggester;
use App\Services\SettingsRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $policyVersion = app(SettingsRepository::class)
                ->string('pd_policy_version', 'draft');

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'phone_is_public' => false,
                'is_profile_public' => false,
                'status' => UserStatus::Active,
                'pd_consent_at' => now(),
                'pd_policy_version' => $policyVersion,
                'marketing_consent_at' => ! empty($data['marketing_consent']) ? now() : null,
            ]);

            $user->assignRole('user');

            return $user;
        });

        DB::afterCommit(fn () => event(new UserRegisteredForActivity($user)));
        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    public function checkNickname(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32'],
        ]);

        /** @var NicknameSuggester $suggester */
        $suggester = app(NicknameSuggester::class);

        return response()->json($suggester->check($validated['name']));
    }
}
