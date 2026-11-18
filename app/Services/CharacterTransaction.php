<?php

namespace App\Services;

use App\Models\GameCharacter;
use App\Models\GameCharacterJob;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * The single mechanism that opens a write transaction for characters and takes
 * the row locks (STAGE-1 §6.3.3, §6.3.4).
 *
 * Ownership follows the Laravel transaction level:
 *  - level 0: this class owns the transaction; it commits, rolls back and
 *    retries transient deadlock/lock-wait errors;
 *  - level > 0: the outer code owns the transaction; this class only joins it
 *    (Laravel opens a savepoint), never commits and never retries, because a
 *    deadlock cancels the whole outer transaction.
 *
 * Row locks are held until the owning transaction commits or rolls back, so an
 * operation that writes several fields and jobs stays atomic.
 */
class CharacterTransaction
{
    private const MAX_ATTEMPTS = 3;

    private static bool $running = false;

    /**
     * @template TReturn
     *
     * @param  Closure(CharacterTransaction): TReturn  $operation
     * @return TReturn
     */
    public function run(User $account, Closure $operation): mixed
    {
        if (self::$running) {
            throw new LogicException('CharacterTransaction::run() is not re-entrant.');
        }

        self::$running = true;

        try {
            return DB::transactionLevel() > 0
                ? $this->join($operation)
                : $this->own($operation);
        } finally {
            self::$running = false;
        }
    }

    /**
     * Current, locking read of one character row.
     */
    public function lockCharacter(int $id, bool $withTrashed = false): ?GameCharacter
    {
        $this->assertInsideTransaction();

        $query = $withTrashed ? GameCharacter::withTrashed() : GameCharacter::query();

        return $query->whereKey($id)->lockForUpdate()->first();
    }

    /**
     * Locking read of several characters in ascending id order (fixed lock
     * order prevents mutual deadlocks between apply and rollback).
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, GameCharacter>
     */
    public function lockCharacters(array $ids): Collection
    {
        $this->assertInsideTransaction();

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        if ($ids === []) {
            return new Collection;
        }

        return GameCharacter::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Locking read of the job rows of one character in job order.
     *
     * @return Collection<int, GameCharacterJob>
     */
    public function lockJobs(int $characterId): Collection
    {
        $this->assertInsideTransaction();

        return GameCharacterJob::query()
            ->where('game_character_id', $characterId)
            ->orderBy('job')
            ->lockForUpdate()
            ->get();
    }

    public function level(): int
    {
        return DB::transactionLevel();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(CharacterTransaction): TReturn  $operation
     * @return TReturn
     */
    private function own(Closure $operation): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(fn () => $operation($this));
            } catch (Throwable $e) {
                if ($attempt >= self::MAX_ATTEMPTS || ! $this->isTransient($e)) {
                    throw $e;
                }

                usleep(50_000 * $attempt);
            }
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(CharacterTransaction): TReturn  $operation
     * @return TReturn
     */
    private function join(Closure $operation): mixed
    {
        return DB::transaction(fn () => $operation($this));
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Character locks may only be taken inside CharacterTransaction::run().');
        }
    }

    private function isTransient(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof QueryException) {
                $code = (int) ($current->errorInfo[1] ?? 0);

                if (in_array($code, [1205, 1213], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
