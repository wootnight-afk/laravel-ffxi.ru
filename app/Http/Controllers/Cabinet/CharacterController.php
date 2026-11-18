<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGameCharacterRequest;
use App\Http\Requests\UpdateGameCharacterRequest;
use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Services\CharacterContext;
use App\Services\GameCharacterSaver;
use App\Services\LegacyGameDataImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Character management of the cabinet (STAGE-1 §2).
 *
 * Ownership is checked by GameCharacterPolicy on every action; the route-bound
 * character is resolved by implicit binding, so a foreign id yields 403 and a
 * soft-deleted character yields 404. All writes go through GameCharacterSaver.
 */
class CharacterController extends Controller
{
    public function __construct(
        private readonly GameCharacterSaver $saver,
        private readonly LegacyGameDataImporter $importer,
        private readonly CharacterContext $context,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GameCharacter::class);

        $user = $request->user();

        $characters = $user->gameCharacters()
            ->with(['server', 'jobs'])
            ->get()
            ->sortBy(fn (GameCharacter $character): string => sprintf(
                '%05d-%s-%s',
                (int) $character->server->sort_order,
                mb_strtolower((string) $character->server->name),
                mb_strtolower($character->name),
            ))
            ->values();

        return view('cabinet.characters.index', [
            'characters' => $characters,
            'servers' => GameServer::query()->active()->ordered()->get(),
            'legacy' => $this->importer->pendingFor($user, $characters->count()),
            'activeCharacterId' => $this->context->for($user)?->getKey(),
        ]);
    }

    public function show(GameCharacter $character): View
    {
        Gate::authorize('view', $character);

        $character->load(['server', 'jobs']);

        return view('cabinet.characters.show', [
            'character' => $character,
            'servers' => $this->serversFor($character),
            'levels' => $character->jobs
                ->mapWithKeys(fn ($job): array => [$job->job->value => $job->level])
                ->all(),
            'activeCharacterId' => $this->context->for(request()->user())?->getKey(),
        ]);
    }

    public function store(StoreGameCharacterRequest $request): RedirectResponse
    {
        $character = $this->saver->create($request->user(), $request->validated());

        return redirect()
            ->route('cabinet.characters.show', $character)
            ->with('status', __('site.cabinet.characters.flash.created'));
    }

    public function update(UpdateGameCharacterRequest $request, GameCharacter $character): RedirectResponse
    {
        $this->saver->update($request->user(), $character, $request->validated());

        return redirect()
            ->route('cabinet.characters.show', $character)
            ->with('status', __('site.cabinet.characters.flash.updated'));
    }

    public function destroy(GameCharacter $character): RedirectResponse
    {
        Gate::authorize('delete', $character);

        $this->saver->delete(request()->user(), $character);

        return redirect()
            ->route('cabinet.characters.index')
            ->with('status', __('site.cabinet.characters.flash.deleted'));
    }

    /**
     * Active servers plus the character's own server, so a deactivated server
     * never blocks saving a character that already lives on it.
     *
     * @return Collection<int, GameServer>
     */
    private function serversFor(GameCharacter $character): Collection
    {
        return GameServer::query()
            ->where('is_active', true)
            ->orWhereKey($character->game_server_id)
            ->ordered()
            ->get();
    }
}
