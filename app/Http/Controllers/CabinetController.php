<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageProcessingException;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AvatarUploader;
use App\Services\ContentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CabinetController extends Controller
{
    /** @var array<int, string> */
    public const TABS = ['profile', 'social', 'news', 'gallery', 'events', 'security', 'danger'];

    public function __construct(
        private readonly AvatarUploader $uploader,
        private readonly ContentRenderer $renderer,
    ) {}

    public function show(string $tab = 'profile'): View
    {
        if (! in_array($tab, self::TABS, true)) {
            abort(404);
        }

        $user = auth()->user();

        return view('cabinet.show', [
            'user' => $user,
            'tab' => $tab,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $user->fill([
            'race' => $data['race'] ?? null,
            'main_job' => $data['main_job'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_is_public' => $request->boolean('phone_is_public'),
            'is_profile_public' => $request->boolean('is_profile_public'),
        ]);

        $newLegend = $data['legend'] ?? null;
        if ($newLegend !== $user->legend) {
            $user->legend = $newLegend;
            $user->legend_html = $newLegend ? $this->renderer->render($newLegend) : null;
        }

        $user->save();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'profile'])
            ->with('status', 'Профиль сохранён.');
    }

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'max:4096'],
        ]);

        try {
            $this->uploader->upload($request->file('avatar'), auth()->user());
        } catch (ImageProcessingException $e) {
            return back()->withErrors(['avatar' => $e->getMessage()]);
        }

        return redirect()
            ->route('cabinet.tab', ['tab' => 'profile'])
            ->with('status', 'Аватар обновлён.');
    }

    public function deleteAvatar(): RedirectResponse
    {
        $this->uploader->remove(auth()->user());

        return redirect()
            ->route('cabinet.tab', ['tab' => 'profile'])
            ->with('status', 'Аватар удалён.');
    }
}
