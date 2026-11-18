<?php

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use App\Services\CharacterTransaction;
use App\Services\GameCharacterSaver;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->server = GameServer::factory()->create();
    $this->saver = app(GameCharacterSaver::class);
});

function characterData(GameServer $server, string $name = 'Bugor'): array
{
    return ['name' => $name, 'game_server_id' => $server->id, 'is_public' => true];
}

it('commits its own transaction at level zero', function () {
    $this->saver->create($this->user, characterData($this->server));

    expect(GameCharacter::query()->count())->toBe(1);
});

it('rolls the whole operation back on failure', function () {
    $transaction = app(CharacterTransaction::class);

    expect(function () use ($transaction) {
        $transaction->run($this->user, function () {
            GameCharacter::query()->create([
                'user_id' => $this->user->id,
                'game_server_id' => $this->server->id,
                'name' => 'Broken',
                'is_public' => true,
            ]);

            throw new RuntimeException('boom');
        });
    })->toThrow(RuntimeException::class);

    expect(GameCharacter::query()->count())->toBe(0);
});

it('joins an outer transaction without committing it', function () {
    try {
        DB::transaction(function () {
            $this->saver->create($this->user, characterData($this->server));

            expect(GameCharacter::query()->count())->toBe(1);

            throw new RuntimeException('outer rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(GameCharacter::query()->count())->toBe(0);
});

it('rolls back only its own savepoint inside an outer transaction', function () {
    DB::transaction(function () {
        $keep = User::factory()->create(['email' => 'keep@example.com']);

        try {
            DB::transaction(function () {
                $this->saver->create($this->user, characterData($this->server));

                throw new RuntimeException('inner rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        expect(GameCharacter::query()->count())->toBe(0)
            ->and(User::query()->whereKey($keep->id)->exists())->toBeTrue();
    });
});

it('forbids recursive entry into the mechanism', function () {
    $transaction = app(CharacterTransaction::class);

    expect(function () use ($transaction) {
        $transaction->run($this->user, function (CharacterTransaction $inner) {
            $inner->run($this->user, fn () => null);
        });
    })->toThrow(LogicException::class);
});

it('returns a current locking read inside the mechanism', function () {
    $character = GameCharacter::factory()->create(['user_id' => $this->user->id]);

    $locked = app(CharacterTransaction::class)->run(
        $this->user,
        fn (CharacterTransaction $tx) => $tx->lockCharacter((int) $character->getKey()),
    );

    expect($locked?->getKey())->toBe($character->getKey());
});

it('applies all fields and jobs atomically', function () {
    $this->saver->create($this->user, [
        'name' => 'Atomic',
        'game_server_id' => $this->server->id,
        'is_public' => true,
        'race' => 'hume',
        'main_job' => 'PLD',
        'jobs' => ['PLD' => 75, 'WHM' => 40],
        'legend' => 'Текст',
    ]);

    $character = GameCharacter::query()->firstOrFail();

    expect($character->jobs()->count())->toBe(2)
        ->and($character->race?->value)->toBe('hume')
        ->and($character->legend_html)->not->toBeNull();
});
