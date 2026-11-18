<?php

use Illuminate\Support\Facades\File;

/**
 * Guards the write boundary of the active-character pointer (STAGE-2 §9).
 *
 * The pointer may be written only by CharacterContext; the deletion observer is
 * the single exception, and it delegates to the same service. Reads are
 * allowed everywhere, so nothing may forbid the model itself.
 *
 * @return array<int, string>
 */
function activeCharacterWriteViolations(string $path, string $contents): array
{
    $allowed = [
        'app/Services/CharacterContext.php',
    ];

    if (! str_contains($contents, 'UserActiveCharacter')
        && ! str_contains($contents, 'user_active_characters')) {
        return [];
    }

    if (in_array($path, $allowed, true) || str_starts_with($path, 'database/')) {
        return [];
    }

    $patterns = [
        '/UserActiveCharacter::(?:create|insert|upsert|firstOrCreate|updateOrCreate|destroy|update|delete)\s*\(/',
        '/DB::table\(\s*[\'"]user_active_characters/',
        '/\$(?:pointer|activeCharacter|userActiveCharacter)\s*->\s*(?:save|update|delete|forceDelete)\s*\(/',
    ];

    $found = [];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $contents, $matches) === 1) {
            $found[] = $matches[0];
        }
    }

    return $found;
}

it('keeps every pointer write inside CharacterContext', function () {
    $violations = [];

    foreach (File::allFiles(app_path()) as $file) {
        $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());

        $found = activeCharacterWriteViolations($relative, $file->getContents());

        if ($found !== []) {
            $violations[$relative] = $found;
        }
    }

    expect($violations)->toBe([]);
});

it('detects the pointer write primitives the scanner is meant to catch', function () {
    $prefix = "use App\\Models\\UserActiveCharacter;\n";

    expect(activeCharacterWriteViolations('app/Services/Other.php', $prefix.'UserActiveCharacter::create([]);'))->not->toBeEmpty()
        ->and(activeCharacterWriteViolations('app/Services/Other.php', $prefix."DB::table('user_active_characters')->update([]);"))->not->toBeEmpty()
        ->and(activeCharacterWriteViolations('app/Services/Other.php', $prefix.'$pointer->delete();'))->not->toBeEmpty();
});

it('does not flag reads of the pointer', function () {
    $prefix = "use App\\Models\\UserActiveCharacter;\n";

    expect(activeCharacterWriteViolations('app/Services/Other.php', $prefix.'UserActiveCharacter::query()->whereKey(1)->first();'))->toBe([])
        ->and(activeCharacterWriteViolations('app/Services/Other.php', $prefix.'$pointer?->character;'))->toBe([]);
});
