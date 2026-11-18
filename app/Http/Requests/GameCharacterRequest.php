<?php

namespace App\Http\Requests;

use App\Enums\CharacterRace;
use App\Enums\GameJob;
use App\Models\GameCharacter;
use App\Models\GameServer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation of a character form (STAGE-1 §3.5).
 *
 * The owner is never accepted from the request. The server must be active, or
 * be the server the character is already saved on: the comparison uses the
 * value from the database, not the request, so a substituted inactive server
 * cannot slip through. Creating a character has no "current server", so only
 * active servers are accepted.
 */
abstract class GameCharacterRequest extends FormRequest
{
    protected function currentServerId(): ?int
    {
        $character = $this->route('character');

        return $character instanceof GameCharacter ? (int) $character->game_server_id : null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }

        $jobs = $this->input('jobs');

        if (! is_array($jobs)) {
            return;
        }

        $clean = [];

        foreach ($jobs as $job => $level) {
            if ($level === null || $level === '') {
                continue;
            }

            $clean[mb_strtoupper(trim((string) $job))] = $level;
        }

        $this->merge(['jobs' => $clean]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currentServer = $this->currentServerId();

        $serverRule = $currentServer === null
            ? Rule::exists(GameServer::class, 'id')->where('is_active', true)
            : Rule::exists(GameServer::class, 'id')->where(function ($query) use ($currentServer): void {
                $query->where('is_active', true)->orWhere('id', $currentServer);
            });

        return [
            'name' => ['required', 'string', 'min:2', 'max:24'],
            'game_server_id' => ['required', 'integer', $serverRule],
            'is_public' => ['sometimes', 'boolean'],
            'race' => ['nullable', Rule::enum(CharacterRace::class)],
            'main_job' => ['nullable', Rule::enum(GameJob::class)],
            'jobs' => ['array'],
            'jobs.*' => ['nullable', 'integer', 'min:1', 'max:99'],
            'legend' => ['nullable', 'string', 'max:4000'],
            'legend_ru' => ['nullable', 'string', 'max:4000'],
            'legend_en' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateJobs($validator);
                $this->validateName($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('site.cabinet.characters.fields.name'),
            'game_server_id' => __('site.cabinet.characters.fields.server'),
            'race' => __('site.cabinet.characters.fields.race'),
            'main_job' => __('site.cabinet.characters.fields.main_job'),
            'jobs' => __('site.cabinet.characters.fields.jobs'),
            'legend' => __('site.cabinet.characters.fields.legend'),
            'legend_ru' => __('site.cabinet.characters.fields.legend_ru'),
            'legend_en' => __('site.cabinet.characters.fields.legend_en'),
        ];
    }

    private function validateJobs(Validator $validator): void
    {
        $jobs = $this->input('jobs');
        $jobs = is_array($jobs) ? $jobs : [];

        foreach (array_keys($jobs) as $job) {
            if (GameJob::tryFrom(mb_strtoupper((string) $job)) === null) {
                $validator->errors()->add('jobs', __('site.cabinet.characters.errors.job_invalid'));

                return;
            }
        }

        $mainJob = $this->input('main_job');

        if ($mainJob === null || $mainJob === '') {
            return;
        }

        if (! array_key_exists(mb_strtoupper((string) $mainJob), $jobs)) {
            $validator->errors()->add('main_job', __('site.cabinet.characters.errors.main_job_not_in_jobs'));
        }
    }

    private function validateName(Validator $validator): void
    {
        if ($validator->errors()->has('name') || $validator->errors()->has('game_server_id')) {
            return;
        }

        // The name must be unique per server, case-insensitively, including
        // soft-deleted rows: they keep the name in the database unique index.
        $query = GameCharacter::withTrashed()
            ->where('game_server_id', $this->integer('game_server_id'))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $this->input('name'))]);

        $character = $this->route('character');

        if ($character instanceof GameCharacter) {
            $query->whereKeyNot($character->getKey());
        }

        if ($query->exists()) {
            $validator->errors()->add('name', __('site.cabinet.characters.errors.name_taken'));
        }
    }
}
