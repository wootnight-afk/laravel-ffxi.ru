<?php

use App\Enums\CharacterRace;
use App\Enums\GameJob;

it('contains the 22 jobs of final fantasy xi', function () {
    expect(GameJob::cases())->toHaveCount(22);
});

it('keeps the job codes stable', function () {
    expect(GameJob::codes())->toBe([
        'WAR', 'MNK', 'WHM', 'BLM', 'RDM', 'THF', 'PLD', 'DRK', 'BST', 'BRD', 'RNG',
        'SAM', 'NIN', 'DRG', 'SMN', 'BLU', 'COR', 'PUP', 'DNC', 'SCH', 'GEO', 'RUN',
    ]);
});

it('keeps the race values lowercase to match the legacy account field', function () {
    expect(CharacterRace::values())->toBe(['hume', 'elvaan', 'tarutaru', 'mithra', 'galka']);
});

it('labels races with their in-game proper names', function () {
    expect(CharacterRace::Tarutaru->label())->toBe('Tarutaru')
        ->and(GameJob::PLD->label())->toBe('PLD');
});
