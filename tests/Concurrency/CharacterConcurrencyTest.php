<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Services\CharacterTransaction;
use App\Services\LegacyGameDataImporter;
use App\Services\LegacyGameDataRollback;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Row locks and concurrent rollback (STAGE-1 §8.3).
 *
 * These tests live outside tests/Feature on purpose: RefreshDatabase wraps a
 * test in an uncommitted transaction, so a second connection would see no data
 * and every lock would be held until the end of the test — the check would be
 * meaningless. The suite therefore migrates the database for real and is
 * skipped on any driver other than MySQL.
 */
uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Row locks are verified on MySQL only.');
    }

    $this->seed(RoleAndPermissionSeeder::class);
});

it('holds a real row lock on the character row', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');
    $server = GameServer::factory()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $user->id,
        'game_server_id' => $server->id,
    ]);

    $config = config('database.connections.mysql');

    $second = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    DB::beginTransaction();

    try {
        app(CharacterTransaction::class)->run($user, function (CharacterTransaction $tx) use ($character): void {
            $tx->lockCharacter((int) $character->getKey());
        });

        $second->exec('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            $second->beginTransaction();
            $second->query('SELECT * FROM game_characters WHERE id = '.(int) $character->getKey().' FOR UPDATE')->fetchAll();
            $second->rollBack();
        } catch (PDOException $e) {
            $blocked = true;

            if ($second->inTransaction()) {
                $second->rollBack();
            }
        }

        expect($blocked)->toBeTrue();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

it('does not overwrite an edit made after the transfer', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'race' => 'Hume',
        'main_job' => 'PLD',
        'legend' => 'Старая легенда',
    ]);
    $server = GameServer::factory()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $user->id,
        'game_server_id' => $server->id,
    ]);

    $rows = app(LegacyGameDataImporter::class)->transferTo($user, $character);
    $batchId = $rows[0]->batch_id;

    GameCharacter::query()->whereKey($character->getKey())->update(['race' => 'elvaan']);

    $summary = app(LegacyGameDataRollback::class)->rollback($batchId);

    expect($summary['skipped_modified'])->toBeGreaterThan(0)
        ->and($character->fresh()->race?->value)->toBe('elvaan');
});

it('resets the batch status atomically so the second rollback changes nothing', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'race' => 'Hume',
        'main_job' => null,
        'legend' => null,
    ]);
    $server = GameServer::factory()->create();
    $character = GameCharacter::factory()->create([
        'user_id' => $user->id,
        'game_server_id' => $server->id,
    ]);

    $rows = app(LegacyGameDataImporter::class)->transferTo($user, $character);
    $batchId = $rows[0]->batch_id;

    $rollback = app(LegacyGameDataRollback::class);

    expect($rollback->rollback($batchId)['reverted'])->toBe(1)
        ->and($rollback->rollback($batchId)['reverted'])->toBe(0);
});
