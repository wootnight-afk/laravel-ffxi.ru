<?php

namespace App\Services;

use App\Enums\GameJob;
use App\Models\GameCharacter;
use App\Models\GameCharacterJob;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * User-facing save and delete of a character (STAGE-1 §6.3.3, level 2).
 *
 * All writes go through CharacterTransaction: one transaction per account, the
 * character row locked before it is touched, and jobs synced inside the same
 * transaction so the whole set is applied atomically.
 */
class GameCharacterSaver
{
    public function __construct(
        private readonly CharacterTransaction $transaction,
        private readonly ContentRenderer $renderer,
        private readonly CharacterContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $account, array $data): GameCharacter
    {
        return $this->transaction->run($account, function () use ($account, $data) {
            $character = new GameCharacter;
            $character->user_id = $account->getKey();
            $character->is_public = false;

            $this->fill($character, $data);
            $character->save();

            $this->syncJobs($character, $this->normalizedJobs($data['jobs'] ?? []));

            return $character;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $account, GameCharacter $character, array $data): GameCharacter
    {
        return $this->transaction->run($account, function (CharacterTransaction $tx) use ($character, $data) {
            $locked = $tx->lockCharacter((int) $character->getKey());

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(GameCharacter::class);
            }

            $this->fill($locked, $data);
            $locked->save();

            if (array_key_exists('jobs', $data)) {
                $this->syncJobs($locked, $this->normalizedJobs((array) $data['jobs']));
            }

            return $locked;
        });
    }

    public function delete(User $account, GameCharacter $character): void
    {
        $this->transaction->run($account, function (CharacterTransaction $tx) use ($character): void {
            $locked = $tx->lockCharacter((int) $character->getKey());

            if ($locked === null) {
                return;
            }

            $locked->delete();

            // Same transaction as `deleted_at`: the pointer can never survive
            // the character it names, even if the observer is bypassed.
            $this->context->forgetCharacter((int) $locked->getKey());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(GameCharacter $character, array $data): void
    {
        $character->name = (string) $data['name'];
        $character->game_server_id = (int) $data['game_server_id'];

        // Fields that are absent from the input are left untouched: a partial
        // update must never wipe values the caller did not mention.
        if (array_key_exists('is_public', $data)) {
            $character->is_public = (bool) $data['is_public'];
        }

        if (array_key_exists('race', $data)) {
            $character->race = $data['race'];
        }

        if (array_key_exists('main_job', $data)) {
            $character->main_job = $data['main_job'];
        }

        // Localized legends are independent: a key that is absent from the
        // input is left untouched, so editing one language never clears the
        // other one.
        foreach (['ru', 'en'] as $locale) {
            $key = 'legend_'.$locale;

            if (! array_key_exists($key, $data)) {
                continue;
            }

            $text = $data[$key];
            $text = is_string($text) && trim($text) !== '' ? $text : null;

            if ($text === $character->{$key}) {
                continue;
            }

            // The HTML is derived, never accepted from the request: it is
            // recomputed only when the Markdown actually changed.
            $character->{$key} = $text;
            $character->{$key.'_html'} = $text === null ? null : $this->renderer->render($text);
        }

        // The single `legend` column is still accepted while the localized
        // versions are rolled out; it keeps its previous behaviour.
        if (! array_key_exists('legend', $data)) {
            return;
        }

        $legend = $data['legend'];
        $legend = is_string($legend) && trim($legend) !== '' ? $legend : null;

        if ($legend !== $character->legend) {
            $character->legend = $legend;
            $character->legend_html = $legend === null ? null : $this->renderer->render($legend);
        }
    }

    /**
     * @param  array<string, mixed>  $jobs
     * @return array<string, int>
     */
    private function normalizedJobs(array $jobs): array
    {
        $normalized = [];

        foreach ($jobs as $job => $level) {
            $code = GameJob::tryFrom(mb_strtoupper((string) $job));

            if ($code === null || ! is_numeric($level)) {
                continue;
            }

            $value = (int) $level;

            if ($value < 1 || $value > 99) {
                continue;
            }

            $normalized[$code->value] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, int>  $jobs
     */
    private function syncJobs(GameCharacter $character, array $jobs): void
    {
        $character->jobs()->whereNotIn('job', array_keys($jobs) ?: [''])->delete();

        foreach ($jobs as $code => $level) {
            GameCharacterJob::query()->updateOrCreate(
                ['game_character_id' => $character->getKey(), 'job' => $code],
                ['level' => $level],
            );
        }

        $character->unsetRelation('jobs');
    }
}
