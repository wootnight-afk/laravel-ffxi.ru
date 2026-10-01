<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialLinkRequest;
use App\Models\UserSocialLink;
use App\Services\SocialLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SocialLinkController extends Controller
{
    public function __construct(
        private readonly SocialLinkService $service,
    ) {}

    public function store(StoreSocialLinkRequest $request): RedirectResponse
    {
        Gate::authorize('create', UserSocialLink::class);

        $user = $request->user();
        $data = $request->validated();

        $this->service->add(
            $user,
            $data['type'],
            $data['username'] ?? null,
            $data['url'] ?? null,
            $data['label'] ?? null,
        );

        return redirect()
            ->route('cabinet.tab', ['tab' => 'social'])
            ->with('status', 'Ссылка добавлена.');
    }

    public function update(StoreSocialLinkRequest $request, UserSocialLink $link): RedirectResponse
    {
        Gate::authorize('update', $link);

        $data = $request->validated();

        $url = $this->service->buildUrl(
            $data['type'],
            $data['username'] ?? null,
            $data['url'] ?? null,
        );

        $link->forceFill([
            'type' => $data['type'],
            'username' => $data['username'] ?? null,
            'url' => $url,
            'label' => $data['label'] ?? null,
        ])->save();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'social'])
            ->with('status', 'Ссылка обновлена.');
    }

    public function toggle(UserSocialLink $link): RedirectResponse
    {
        Gate::authorize('update', $link);

        $link->forceFill(['is_visible' => ! $link->is_visible])->save();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'social'])
            ->with('status', $link->is_visible ? 'Ссылка показана в профиле.' : 'Ссылка скрыта.');
    }

    public function destroy(UserSocialLink $link): RedirectResponse
    {
        Gate::authorize('delete', $link);

        $link->delete();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'social'])
            ->with('status', 'Ссылка удалена.');
    }
}
