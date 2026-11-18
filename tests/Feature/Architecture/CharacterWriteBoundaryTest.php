<?php

use Illuminate\Support\Facades\File;

/**
 * Guards the write boundary of character data (STAGE-1 §8.3).
 *
 * The check is built on two layers: a dependency-free scan of `app/` for the
 * write primitives, and a meta-test proving that the scan finds real writes
 * while leaving reads alone. Reading characters is explicitly allowed
 * everywhere, so no rule may forbid the model itself.
 *
 * @return array<int, string>
 */
function characterWriteViolations(string $path, string $contents): array
{
    $allowed = [
        'app/Services/CharacterTransaction.php',
        'app/Services/GameCharacterSaver.php',
        'app/Services/LegacyGameDataImporter.php',
        'app/Services/LegacyGameDataRollback.php',
        // One-off redistribution of the legacy legend into the localized
        // columns; it runs inside CharacterTransaction and is idempotent.
        'app/Services/LegendLocaleBackfill.php',
    ];

    if (! str_contains($contents, 'GameCharacter') && ! str_contains($contents, 'game_character_jobs')) {
        return [];
    }

    if (in_array($path, $allowed, true) || str_starts_with($path, 'database/')) {
        return [];
    }

    $patterns = [
        '/GameCharacter::(?:create|insert|upsert|firstOrCreate|updateOrCreate|destroy)\s*\(/',
        '/GameCharacterJob::(?:create|insert|upsert|firstOrCreate|updateOrCreate|destroy)\s*\(/',
        '/\$(?:character|locked|gameCharacter|game_character)\s*->\s*(?:save|saveQuietly|update|delete|forceDelete|restore|push|increment|decrement)\s*\(/',
        '/DB::table\(\s*[\'"]game_character/',
        '/DB::(?:statement|update|insert|delete)\s*\(/',
        '/jobs\(\)\s*->\s*(?:create|save|saveMany|sync|delete|attach|detach)\s*\(/',
    ];

    $found = [];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $contents, $matches) === 1) {
            $found[] = $matches[0];
        }
    }

    return $found;
}

it('keeps every character write inside the single mechanism', function () {
    $violations = [];

    foreach (File::allFiles(app_path()) as $file) {
        $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());

        $found = characterWriteViolations($relative, $file->getContents());

        if ($found !== []) {
            $violations[$relative] = $found;
        }
    }

    expect($violations)->toBe([]);
});

it('detects every write primitive the scanner is meant to catch', function () {
    $prefix = "use App\\Models\\GameCharacter;\n";

    expect(characterWriteViolations('app/Services/Other.php', $prefix.'$character->update([]);'))->not->toBeEmpty()
        ->and(characterWriteViolations('app/Services/Other.php', $prefix.'$character->save();'))->not->toBeEmpty()
        ->and(characterWriteViolations('app/Services/Other.php', $prefix.'GameCharacter::upsert([]);'))->not->toBeEmpty()
        ->and(characterWriteViolations('app/Services/Other.php', $prefix."DB::table('game_characters')->update([]);"))->not->toBeEmpty()
        ->and(characterWriteViolations('app/Services/Other.php', $prefix.'$character->jobs()->sync([]);'))->not->toBeEmpty()
        ->and(characterWriteViolations('app/Services/Other.php', $prefix.'$locked->delete();'))->not->toBeEmpty();
});

it('does not flag reads or writes of other models', function () {
    $prefix = "use App\\Models\\GameCharacter;\n";

    expect(characterWriteViolations('app/Services/Directory.php', $prefix.'GameCharacter::query()->with("jobs")->paginate();'))->toBe([])
        ->and(characterWriteViolations('app/Services/Directory.php', $prefix.'$character->jobs->count();'))->toBe([])
        ->and(characterWriteViolations('app/Services/Directory.php', $prefix.'GameCharacter::withTrashed()->find(1);'))->toBe([])
        ->and(characterWriteViolations('app/Services/Directory.php', $prefix.'$user->update([]);'))->toBe([])
        ->and(characterWriteViolations('app/Services/Directory.php', "DB::table('users')->update([]);"))->toBe([]);
});

it('does not restrict a read-only service', function () {
    $contents = <<<'PHP'
        use App\Models\GameCharacter;

        final class CharacterDirectory
        {
            public function paginate()
            {
                return GameCharacter::query()->with('jobs')->public()->paginate(20);
            }
        }
        PHP;

    expect(characterWriteViolations('app/Services/CharacterDirectory.php', $contents))->toBe([]);
});
