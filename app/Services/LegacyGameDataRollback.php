<?php

namespace App\Services;

use App\Models\GameCharacter;
use App\Models\GameCharacterJob;
use App\Models\LegacyGameDataTransfer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rollback of a legacy transfer batch (STAGE-1 §6.3.3, §6.3.4).
 *
 * The control hash is compared with the value read under a locking read inside
 * the account transaction, so a concurrent edit is either seen (and the field
 * becomes `skipped_modified`) or waited for — never overwritten. A failure
 * rolls the whole account transaction back, leaving neither fields nor journal
 * statuses partially changed.
 */
class LegacyGameDataRollback
{
    public function __construct(
        private readonly CharacterTransaction $transaction,
        private readonly LegacyGameDataValues $values,
    ) {}

    /**
     * @return array<string, int>
     */
    public function rollback(string $batchId): array
    {
        $summary = [
            LegacyGameDataTransfer::STATUS_REVERTED => 0,
            LegacyGameDataTransfer::STATUS_SKIPPED_MODIFIED => 0,
            LegacyGameDataTransfer::STATUS_SKIPPED_CONFLICT => 0,
            'errors' => 0,
        ];

        $characterIds = LegacyGameDataTransfer::query()
            ->where('batch_id', $batchId)
            ->where('status', LegacyGameDataTransfer::STATUS_APPLIED)
            ->distinct()
            ->orderBy('character_id')
            ->pluck('character_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        sort($characterIds);

        foreach ($characterIds as $characterId) {
            $character = GameCharacter::withTrashed()->with('user')->find($characterId);

            if ($character === null || $character->user === null) {
                continue;
            }

            try {
                foreach ($this->rollbackCharacter($batchId, $character) as $status => $count) {
                    $summary[$status] = ($summary[$status] ?? 0) + $count;
                }
            } catch (Throwable $e) {
                // Recorded outside the aborted transaction for the same reason
                // as in the importer: a log row inside it would vanish.
                $summary['errors']++;
                Log::error('Legacy game data rollback failed for a character.', [
                    'character_id' => $characterId,
                    'batch_id' => $batchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function rollbackCharacter(string $batchId, GameCharacter $character): array
    {
        $counts = [];

        $this->transaction->run($character->user, function (CharacterTransaction $tx) use ($batchId, $character, &$counts): void {
            $locked = $tx->lockCharacter((int) $character->getKey(), withTrashed: true);

            if ($locked === null) {
                return;
            }

            $records = LegacyGameDataTransfer::query()
                ->where('batch_id', $batchId)
                ->where('character_id', $locked->getKey())
                ->where('status', LegacyGameDataTransfer::STATUS_APPLIED)
                ->orderBy('id')
                ->get();

            $jobsLocked = false;
            $writes = [];
            $applied = [];

            $groups = $records->groupBy(
                fn (LegacyGameDataTransfer $record): string => (string) ($record->field_group ?: $record->field),
            );

            foreach ($groups as $members) {
                $matches = true;

                foreach ($members as $member) {
                    if ($member->field === 'jobs') {
                        if (! $jobsLocked) {
                            $tx->lockJobs((int) $locked->getKey());
                            $jobsLocked = true;
                        }

                        $level = GameCharacterJob::query()
                            ->where('game_character_id', $locked->getKey())
                            ->where('job', (string) $member->note)
                            ->value('level');

                        if ($level === null
                            || $this->values->controlHash('jobs', (int) $level, (string) $member->note) !== $member->applied_hash) {
                            $matches = false;

                            break;
                        }

                        continue;
                    }

                    $current = $this->values->currentValue($locked, (string) $member->field);

                    if ($this->values->controlHash((string) $member->field, $current) !== $member->applied_hash) {
                        $matches = false;

                        break;
                    }
                }

                if (! $matches) {
                    foreach ($members as $member) {
                        $counts[LegacyGameDataTransfer::STATUS_SKIPPED_MODIFIED] =
                            ($counts[LegacyGameDataTransfer::STATUS_SKIPPED_MODIFIED] ?? 0) + 1;
                    }

                    continue;
                }

                foreach ($members as $member) {
                    if ($member->field === 'jobs') {
                        $deleted = GameCharacterJob::query()
                            ->where('game_character_id', $locked->getKey())
                            ->where('job', (string) $member->note)
                            ->where('level', (int) $member->source_value)
                            ->delete();

                        if ($deleted === 1) {
                            $this->markReverted($member, $counts);
                        }

                        continue;
                    }

                    $writes[(string) $member->field] = $member->target_value_before;
                    $applied[] = $member;
                }
            }

            if ($writes !== []) {
                $locked->fill($writes);
                $locked->save();

                foreach ($applied as $member) {
                    $this->markReverted($member, $counts);
                }
            }
        });

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function markReverted(LegacyGameDataTransfer $record, array &$counts): void
    {
        $updated = LegacyGameDataTransfer::query()
            ->whereKey($record->getKey())
            ->where('status', LegacyGameDataTransfer::STATUS_APPLIED)
            ->update([
                'status' => LegacyGameDataTransfer::STATUS_REVERTED,
                'reverted_at' => now(),
                'reverted_by' => auth()->id(),
            ]);

        if ($updated === 1) {
            $counts[LegacyGameDataTransfer::STATUS_REVERTED] =
                ($counts[LegacyGameDataTransfer::STATUS_REVERTED] ?? 0) + 1;
        }
    }
}
