<?php

namespace App\Services;

use App\Enums\GameJob;
use App\Models\GameCharacter;
use App\Models\GameCharacterJob;
use App\Models\LegacyGameDataTransfer;
use App\Models\User;
use App\Support\LegendLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Transfer of the legacy account game data to a character (STAGE-1 §6).
 *
 * Rules by number of characters (STAGE-1 §6.2): none — nothing to transfer;
 * exactly one — transfer field by field, a non-empty target becomes
 * `skipped_conflict`; two or more — never automatic, the owner chooses a
 * character manually. Jobs are never transferred from the account (the account
 * has no job levels); `transferJobs()` exists for an explicit caller and is
 * covered by the rollback rules.
 */
class LegacyGameDataImporter
{
    public function __construct(
        private readonly CharacterTransaction $transaction,
        private readonly LegacyGameDataValues $values,
        private readonly ContentRenderer $renderer,
    ) {}

    /**
     * @return array<string, string>
     */
    public function legacyValues(User $user): array
    {
        return $this->values->legacyValues($user);
    }

    /**
     * Data for the manual choice block in the cabinet: legacy values are shown
     * only when the account has several characters and the transfer cannot be
     * unambiguous.
     *
     * @return array{values: array<string, string>, needs_choice: bool}|null
     */
    public function pendingFor(User $user, int $characterCount): ?array
    {
        $legacy = $this->values->legacyValues($user);

        if ($legacy === [] || $characterCount < 2) {
            return null;
        }

        // A field with an applied transfer is already bound to a character.
        // Showing it again would claim the data is unbound and invite a second,
        // conflicting transfer.
        $bound = LegacyGameDataTransfer::query()
            ->where('user_id', $user->getKey())
            ->where('status', LegacyGameDataTransfer::STATUS_APPLIED)
            ->pluck('field')
            ->all();

        if (array_diff(array_keys($legacy), $bound) === []) {
            return null;
        }

        return ['values' => $legacy, 'needs_choice' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function planFor(User $user): array
    {
        $legacy = $this->values->legacyValues($user);

        if ($legacy === []) {
            return [];
        }

        $characters = $user->gameCharacters()->orderBy('id')->get();

        $plan = [
            'user_id' => $user->getKey(),
            'user' => $user->name,
            'legacy' => $legacy,
            'characters' => $characters->count(),
            'reason' => null,
            'actions' => [],
        ];

        if ($characters->isEmpty()) {
            $plan['reason'] = 'no_character';

            return $plan;
        }

        if ($characters->count() > 1) {
            $plan['reason'] = 'multiple_characters';

            return $plan;
        }

        $character = $characters->first();

        foreach ($legacy as $field => $value) {
            $current = $this->values->currentValue($character, $field);

            $plan['actions'][] = [
                'field' => $field,
                'status' => $this->values->normalized($field, $current) !== null
                    ? LegacyGameDataTransfer::STATUS_SKIPPED_CONFLICT
                    : LegacyGameDataTransfer::STATUS_APPLIED,
                'source' => $value,
                'target' => $this->values->scalar($current),
            ];
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $stats = [
            'accounts' => 0,
            'zero' => 0,
            'one' => 0,
            'many' => 0,
            'fields' => ['race' => 0, 'main_job' => 0, 'legend' => 0],
            'conflicts' => ['race' => 0, 'main_job' => 0, 'legend' => 0],
            'planned' => 0,
        ];

        $this->accountsWithLegacy()->chunkById(200, function ($users) use (&$stats): void {
            foreach ($users as $user) {
                $plan = $this->planFor($user);

                if ($plan === []) {
                    continue;
                }

                $stats['accounts']++;

                foreach (array_keys($plan['legacy']) as $field) {
                    $stats['fields'][$field]++;
                }

                if ($plan['reason'] === 'no_character') {
                    $stats['zero']++;

                    continue;
                }

                if ($plan['reason'] === 'multiple_characters') {
                    $stats['many']++;

                    continue;
                }

                $stats['one']++;

                foreach ($plan['actions'] as $action) {
                    if ($action['status'] === LegacyGameDataTransfer::STATUS_APPLIED) {
                        $stats['planned']++;
                    } else {
                        $stats['conflicts'][$action['field']]++;
                    }
                }
            }
        });

        return $stats;
    }

    /**
     * @return array{report: array<string, mixed>, plan: array<int, array<string, mixed>>}
     */
    public function dryRun(int $limit = 200): array
    {
        $plan = [];

        $this->accountsWithLegacy()->chunkById(200, function ($users) use (&$plan, $limit): bool {
            foreach ($users as $user) {
                if (count($plan) >= $limit) {
                    return false;
                }

                $row = $this->planFor($user);

                if ($row !== []) {
                    $plan[] = $row;
                }
            }

            return true;
        });

        return ['report' => $this->report(), 'plan' => $plan];
    }

    /**
     * @param  array<int, string>  $overwrite
     * @return array<string, int>
     */
    public function apply(string $batchId, array $overwrite = [], int $limit = 0): array
    {
        $summary = [
            'processed' => 0,
            'applied' => 0,
            LegacyGameDataTransfer::STATUS_SKIPPED_CONFLICT => 0,
            LegacyGameDataTransfer::STATUS_SKIPPED_NO_TARGET => 0,
            'errors' => 0,
        ];

        $this->accountsWithLegacy()->chunkById(200, function ($users) use (&$summary, $batchId, $overwrite, $limit): bool {
            foreach ($users as $user) {
                if ($limit > 0 && $summary['processed'] >= $limit) {
                    return false;
                }

                $summary['processed']++;

                $characters = $user->gameCharacters()->orderBy('id')->get();

                if ($characters->count() !== 1) {
                    $summary[LegacyGameDataTransfer::STATUS_SKIPPED_NO_TARGET] += count($this->values->legacyValues($user));

                    continue;
                }

                try {
                    foreach ($this->transferTo($user, $characters->first(), $batchId, $overwrite) as $row) {
                        $summary[$row->status] = ($summary[$row->status] ?? 0) + 1;
                    }
                } catch (Throwable $e) {
                    // The error is recorded outside the aborted transaction:
                    // a row written inside it would disappear with the rollback.
                    $summary['errors']++;
                    Log::error('Legacy game data transfer failed for an account.', [
                        'user_id' => $user->getKey(),
                        'batch_id' => $batchId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return true;
        });

        return $summary;
    }

    /**
     * Transfer the legacy values of one account to one explicit character.
     *
     * @param  array<int, string>  $overwrite
     * @return array<int, LegacyGameDataTransfer>
     */
    public function transferTo(User $user, GameCharacter $character, ?string $batchId = null, array $overwrite = []): array
    {
        $batchId ??= (string) Str::uuid();
        $overwrite = array_values(array_intersect($overwrite, LegacyGameDataValues::FIELDS));

        return $this->transaction->run($user, function (CharacterTransaction $tx) use ($user, $character, $batchId, $overwrite): array {
            $locked = $tx->lockCharacter((int) $character->getKey());

            if ($locked === null || (int) $locked->user_id !== (int) $user->getKey()) {
                throw new RuntimeException('Character is not available for this account.');
            }

            $legacy = $this->values->legacyValues($user);

            if ($legacy === []) {
                return [];
            }

            $decisions = [];
            $writes = [];

            foreach ($legacy as $field => $value) {
                $before = $this->values->currentValue($locked, $field);
                $hasValue = $this->values->normalized($field, $before) !== null;

                if ($hasValue && ! in_array($field, $overwrite, true)) {
                    $decisions[] = [
                        'field' => $field,
                        'status' => LegacyGameDataTransfer::STATUS_SKIPPED_CONFLICT,
                        'source' => $value,
                        'before' => $before,
                    ];

                    continue;
                }

                $writes[$field] = $value;

                $decisions[] = [
                    'field' => $field,
                    'status' => LegacyGameDataTransfer::STATUS_APPLIED,
                    'source' => $value,
                    'before' => $before,
                ];

                if ($field === 'legend') {
                    $decisions[] = [
                        'field' => 'legend_html',
                        'field_group' => 'legend',
                        'status' => LegacyGameDataTransfer::STATUS_APPLIED,
                        'source' => null,
                        'before' => $locked->legend_html,
                    ];

                    // The page shows the localized legend, so the transferred
                    // text also goes into the version matching its language. A
                    // text whose language cannot be detected stays unchanged in
                    // the primary locale: it is never translated or duplicated.
                    $localized = LegendLanguage::detect((string) $value) === LegendLanguage::EN
                        ? 'legend_en'
                        : 'legend_ru';

                    $decisions[] = [
                        'field' => $localized,
                        'field_group' => 'legend',
                        'status' => LegacyGameDataTransfer::STATUS_APPLIED,
                        'source' => $value,
                        'before' => $locked->{$localized},
                    ];

                    $decisions[] = [
                        'field' => $localized.'_html',
                        'field_group' => 'legend',
                        'status' => LegacyGameDataTransfer::STATUS_APPLIED,
                        'source' => null,
                        'before' => $locked->{$localized.'_html'},
                    ];
                }
            }

            if ($writes === []) {
                return array_map(
                    fn (array $decision): LegacyGameDataTransfer => $this->journal($batchId, $user, $locked, $decision, $locked),
                    $decisions,
                );
            }

            $locked->fill($writes);

            if (array_key_exists('legend', $writes)) {
                $locked->legend_html = $this->renderer->render((string) $writes['legend']) ?: null;

                $localized = LegendLanguage::detect((string) $writes['legend']) === LegendLanguage::EN
                    ? 'legend_en'
                    : 'legend_ru';

                $locked->{$localized} = (string) $writes['legend'];
                $locked->{$localized.'_html'} = $locked->legend_html;
            }

            $locked->save();
            $locked->refresh();

            return array_map(
                fn (array $decision): LegacyGameDataTransfer => $this->journal($batchId, $user, $locked, $decision, $locked),
                $decisions,
            );
        });
    }

    /**
     * Explicit job transfer. The account has no job levels, so the CLI never
     * calls this; it exists for a caller that has a real source and is covered
     * by the same journal and rollback rules.
     *
     * @param  array<string, mixed>  $jobs
     * @return array<int, LegacyGameDataTransfer>
     */
    public function transferJobs(GameCharacter $character, array $jobs, string $batchId): array
    {
        $user = $character->user;

        if ($user === null) {
            throw new RuntimeException('Character has no owner account.');
        }

        return $this->transaction->run($user, function (CharacterTransaction $tx) use ($character, $jobs, $batchId, $user): array {
            $locked = $tx->lockCharacter((int) $character->getKey());

            if ($locked === null) {
                return [];
            }

            $existing = $tx->lockJobs((int) $locked->getKey())
                ->keyBy(fn (GameCharacterJob $row): string => $row->job->value);

            $rows = [];

            foreach ($jobs as $job => $level) {
                $code = GameJob::tryFrom(mb_strtoupper((string) $job));
                $value = (int) $level;

                if ($code === null || $value < 1 || $value > 99) {
                    continue;
                }

                $before = $existing->get($code->value);

                if ($before !== null) {
                    $rows[] = LegacyGameDataTransfer::create([
                        'batch_id' => $batchId,
                        'user_id' => $user->getKey(),
                        'character_id' => $locked->getKey(),
                        'field' => 'jobs',
                        'status' => LegacyGameDataTransfer::STATUS_SKIPPED_CONFLICT,
                        'source_value' => (string) $value,
                        'source_hash' => $this->values->sourceHash('jobs', $code->value.':'.$value),
                        'target_value_before' => (string) $before->level,
                        'target_hash_before' => $this->values->controlHash('jobs', $before->level, $code->value),
                        'note' => $code->value,
                    ]);

                    continue;
                }

                GameCharacterJob::query()->create([
                    'game_character_id' => $locked->getKey(),
                    'job' => $code->value,
                    'level' => $value,
                ]);

                $rows[] = LegacyGameDataTransfer::create([
                    'batch_id' => $batchId,
                    'user_id' => $user->getKey(),
                    'character_id' => $locked->getKey(),
                    'field' => 'jobs',
                    'status' => LegacyGameDataTransfer::STATUS_APPLIED,
                    'source_value' => (string) $value,
                    'source_hash' => $this->values->sourceHash('jobs', $code->value.':'.$value),
                    'target_value_before' => null,
                    'target_hash_before' => $this->values->controlHash('jobs', null, $code->value),
                    'applied_hash' => $this->values->controlHash('jobs', $value, $code->value),
                    'applied_at' => now(),
                    'note' => $code->value,
                ]);
            }

            return $rows;
        });
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function journal(string $batchId, User $user, GameCharacter $character, array $decision, GameCharacter $after): LegacyGameDataTransfer
    {
        $field = (string) $decision['field'];
        $applied = $decision['status'] === LegacyGameDataTransfer::STATUS_APPLIED;

        return LegacyGameDataTransfer::create([
            'batch_id' => $batchId,
            'user_id' => $user->getKey(),
            'character_id' => $character->getKey(),
            'field' => $field,
            'field_group' => $decision['field_group'] ?? ($field === 'legend' ? 'legend' : null),
            'status' => $decision['status'],
            'source_value' => $decision['source'],
            'source_hash' => $decision['source'] === null
                ? null
                : $this->values->sourceHash($field, (string) $decision['source']),
            'target_value_before' => $this->values->scalar($decision['before']),
            'target_hash_before' => $this->values->controlHash($field, $decision['before']),
            'applied_hash' => $applied
                ? $this->values->controlHash($field, $this->values->currentValue($after, $field))
                : null,
            'applied_at' => $applied ? now() : null,
        ]);
    }

    /**
     * @return Builder<User>
     */
    private function accountsWithLegacy(): Builder
    {
        return User::query()->where(function (Builder $query): void {
            $query->where(function (Builder $q): void {
                $q->whereNotNull('race')->where('race', '!=', '');
            })->orWhere(function (Builder $q): void {
                $q->whereNotNull('main_job')->where('main_job', '!=', '');
            })->orWhere(function (Builder $q): void {
                $q->whereNotNull('legend')->where('legend', '!=', '');
            });
        });
    }
}
