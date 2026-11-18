<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\LegacyGameDataTransfer;
use App\Models\User;
use App\Services\LegacyGameDataImporter;
use App\Services\LegacyGameDataRollback;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->server = GameServer::factory()->create();
});

function legacyAccount(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'race' => 'Hume',
        'main_job' => 'PLD',
        'legend' => 'Старая легенда',
    ], $attributes));
}

function legacyCharacter(User $user, GameServer $server, array $attributes = []): GameCharacter
{
    return GameCharacter::factory()->create(array_merge([
        'user_id' => $user->id,
        'game_server_id' => $server->id,
        'name' => 'Bugor',
    ], $attributes));
}

it('reports the distribution without writing anything', function () {
    $user = legacyAccount();
    legacyCharacter($user, $this->server);

    $this->artisan('characters:legacy-game-data', ['--report' => true])
        ->assertSuccessful();

    expect(LegacyGameDataTransfer::query()->count())->toBe(0);
});

it('does not write during a dry run', function () {
    $user = legacyAccount();
    $character = legacyCharacter($user, $this->server);

    $this->artisan('characters:legacy-game-data', ['--dry-run' => true])
        ->assertSuccessful();

    expect(LegacyGameDataTransfer::query()->count())->toBe(0)
        ->and($character->fresh()->race)->toBeNull()
        ->and($character->fresh()->main_job)->toBeNull();
});

it('transfers only into empty fields and journals both sides', function () {
    $user = legacyAccount();
    $character = legacyCharacter($user, $this->server);

    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--force' => true])
        ->assertSuccessful();

    $character->refresh();

    expect($character->race?->value)->toBe('hume')
        ->and($character->main_job)->toBe('PLD')
        ->and($character->legend)->toBe('Старая легенда')
        ->and($character->legend_html)->toContain('Старая легенда');

    $records = LegacyGameDataTransfer::query()->where('status', 'applied')->get();

    expect($records)->not->toBeEmpty()
        ->and($records->pluck('field')->all())
        ->toContain('race')->toContain('main_job')->toContain('legend')->toContain('legend_html');

    $race = $records->firstWhere('field', 'race');
    expect($race->target_value_before)->toBeNull()
        ->and($race->source_value)->toBe('hume')
        ->and($race->applied_hash)->not->toBeNull()
        ->and($race->source_hash)->not->toBeNull()
        ->and($race->applied_hash)->not->toBe($race->source_hash);
});

it('skips a non-empty target field and keeps the character value', function () {
    $user = legacyAccount();
    $character = legacyCharacter($user, $this->server, ['race' => 'elvaan']);

    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--force' => true])
        ->assertSuccessful();

    expect($character->fresh()->race?->value)->toBe('elvaan');

    $conflict = LegacyGameDataTransfer::query()
        ->where('field', 'race')
        ->where('status', 'skipped_conflict')
        ->first();

    expect($conflict)->not->toBeNull()
        ->and($conflict->target_value_before)->toBe('elvaan')
        ->and($conflict->applied_hash)->toBeNull();
});

it('restores the previous value on rollback after overwrite', function () {
    $user = legacyAccount();
    $character = legacyCharacter($user, $this->server, ['race' => 'elvaan']);

    $this->artisan('characters:legacy-game-data', [
        '--apply' => true,
        '--overwrite' => 'race',
        '--force' => true,
    ])->assertSuccessful();

    $batchId = LegacyGameDataTransfer::query()->where('field', 'race')->value('batch_id');

    expect($character->fresh()->race?->value)->toBe('hume');

    $this->artisan('characters:legacy-game-data', ['--rollback' => $batchId])
        ->assertSuccessful();

    expect($character->fresh()->race?->value)->toBe('elvaan')
        ->and(LegacyGameDataTransfer::query()->where('field', 'race')->value('status'))->toBe('reverted');
});

it('never overwrites an edit made after the transfer', function () {
    $user = legacyAccount();
    $character = legacyCharacter($user, $this->server);

    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--force' => true])
        ->assertSuccessful();

    $batchId = LegacyGameDataTransfer::query()->value('batch_id');

    $character->update(['race' => 'elvaan']);

    $this->artisan('characters:legacy-game-data', ['--rollback' => $batchId])
        ->assertSuccessful();

    expect($character->fresh()->race?->value)->toBe('elvaan');
});

it('is idempotent for repeated apply and rollback', function () {
    $user = legacyAccount();
    $character = legacyCharacter($user, $this->server);

    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--force' => true])
        ->assertSuccessful();
    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--force' => true])
        ->assertSuccessful();

    // Contract: each run gets its own batch, and the second run journals
    // conflicts instead of duplicating the transfer (race, main_job, legend),
    // without re-applying the legend_html group.
    $batches = LegacyGameDataTransfer::query()->orderBy('id')->pluck('batch_id')->unique()->values();

    expect(LegacyGameDataTransfer::query()->count())->toBe(9)
        ->and($batches)->toHaveCount(2)
        ->and(LegacyGameDataTransfer::query()->where('batch_id', $batches[0])->where('status', 'applied')->count())->toBe(6)
        ->and(LegacyGameDataTransfer::query()->where('batch_id', $batches[1])->where('status', 'skipped_conflict')->count())->toBe(3);

    // The batch must be selected explicitly: `value('batch_id')` has no ORDER BY
    // and is resolved through the batch_id index, so it may return either run.
    $batchId = $batches[0];

    $this->artisan('characters:legacy-game-data', ['--rollback' => $batchId])
        ->assertSuccessful();
    $this->artisan('characters:legacy-game-data', ['--rollback' => $batchId])
        ->assertSuccessful();

    expect($character->fresh()->race)->toBeNull()
        ->and($character->fresh()->main_job)->toBeNull()
        ->and($character->fresh()->legend)->toBeNull()
        ->and($character->fresh()->legend_html)->toBeNull()
        ->and(LegacyGameDataTransfer::query()->where('batch_id', $batchId)->where('status', 'reverted')->count())->toBe(6);
});

it('skips accounts without a character and with several characters', function () {
    $without = legacyAccount(['email' => 'none@example.com']);
    $withTwo = legacyAccount(['email' => 'two@example.com']);
    legacyCharacter($withTwo, $this->server, ['name' => 'First']);
    legacyCharacter($withTwo, $this->server, ['name' => 'Second']);

    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--force' => true])
        ->assertSuccessful();

    expect(LegacyGameDataTransfer::query()->count())->toBe(0)
        ->and($without->fresh()->race)->toBe('Hume')
        ->and($withTwo->gameCharacters()->count())->toBe(2);
});

it('rejects invalid overwrite values', function () {
    legacyAccount();

    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--overwrite' => 'legend_html', '--force' => true])
        ->assertFailed();
    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--overwrite' => 'jobs', '--force' => true])
        ->assertFailed();
    $this->artisan('characters:legacy-game-data', ['--apply' => true, '--overwrite' => 'unknown', '--force' => true])
        ->assertFailed();
    $this->artisan('characters:legacy-game-data', ['--overwrite' => 'race'])
        ->assertFailed();

    expect(LegacyGameDataTransfer::query()->count())->toBe(0);
});

it('requires exactly one mode', function () {
    $this->artisan('characters:legacy-game-data')->assertFailed();
    $this->artisan('characters:legacy-game-data', ['--report' => true, '--dry-run' => true])->assertFailed();
});

it('transfers jobs explicitly and removes only the imported level on rollback', function () {
    $user = legacyAccount(['race' => null, 'main_job' => null, 'legend' => null]);
    $character = legacyCharacter($user, $this->server);

    $batchId = (string) Str::uuid();

    app(LegacyGameDataImporter::class)->transferJobs($character, ['PLD' => 75], $batchId);

    expect($character->jobs()->count())->toBe(1)
        ->and($character->jobs()->where('job', 'PLD')->value('level'))->toBe(75);

    app(LegacyGameDataRollback::class)->rollback($batchId);

    expect($character->jobs()->count())->toBe(0);
});

it('keeps an imported job whose level was changed manually', function () {
    $user = legacyAccount(['race' => null, 'main_job' => null, 'legend' => null]);
    $character = legacyCharacter($user, $this->server);

    $batchId = (string) Str::uuid();

    app(LegacyGameDataImporter::class)->transferJobs($character, ['PLD' => 75], $batchId);

    $character->jobs()->where('job', 'PLD')->update(['level' => 80]);

    app(LegacyGameDataRollback::class)->rollback($batchId);

    expect($character->jobs()->where('job', 'PLD')->value('level'))->toBe(80);
});

it('stops offering the manual transfer once every value is bound', function () {
    $user = legacyAccount();
    $first = legacyCharacter($user, $this->server, ['name' => 'First']);
    legacyCharacter($user, $this->server, ['name' => 'Second']);

    $importer = app(LegacyGameDataImporter::class);

    expect($importer->pendingFor($user, 2))->not->toBeNull();

    $importer->transferTo($user, $first);

    expect($importer->pendingFor($user, 2))->toBeNull();
});

it('keeps offering the manual transfer while a field is not bound', function () {
    $user = legacyAccount();
    $first = legacyCharacter($user, $this->server, ['name' => 'First', 'race' => 'elvaan']);
    legacyCharacter($user, $this->server, ['name' => 'Second']);

    $importer = app(LegacyGameDataImporter::class);

    $importer->transferTo($user, $first);

    // race stayed a conflict, so the account still holds unbound data; a single
    // character never shows the manual choice block.
    expect($importer->pendingFor($user, 2))->not->toBeNull()
        ->and($importer->pendingFor($user, 1))->toBeNull();
});
