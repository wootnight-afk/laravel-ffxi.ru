<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageProcessingException;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AvatarUploader;
use App\Services\ContentRenderer;
use App\Services\TimezoneOptions;
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
            'timezoneOptions' => $tab === 'profile' ? TimezoneOptions::grouped() : [],
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
            'is_gallery_public' => $request->boolean('is_gallery_public'),
        ]);

        // The two descriptions are independent: a version absent from the
        // request keeps its current text, so editing one language never clears
        // the other (STAGE-1 §17.3).
        foreach (['ru', 'en'] as $locale) {
            $key = 'legend_'.$locale;

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $text = is_string($data[$key]) && trim($data[$key]) !== '' ? $data[$key] : null;

            if ($text === $user->{$key}) {
                continue;
            }

            $user->{$key} = $text;
            $user->{$key.'_html'} = $text === null ? null : $this->renderer->render($text);
        }

        // `legend` remains the single-column fallback while the localized
        // versions are rolled out; its behaviour is unchanged.
        if (array_key_exists('legend', $data)) {
            $newLegend = is_string($data['legend']) && trim($data['legend']) !== '' ? $data['legend'] : null;

            if ($newLegend !== $user->legend) {
                $user->legend = $newLegend;
                $user->legend_html = $newLegend === null ? null : $this->renderer->render($newLegend);
            }
        }

        $user->save();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'profile'])
            ->with('status', __('site.cabinet.flash.profile_saved'));
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
            ->with('status', __('site.cabinet.flash.avatar_updated'));
    }

    public function deleteAvatar(): RedirectResponse
    {
        $this->uploader->remove(auth()->user());

        return redirect()
            ->route('cabinet.tab', ['tab' => 'profile'])
            ->with('status', __('site.cabinet.flash.avatar_deleted'));
    }
}
