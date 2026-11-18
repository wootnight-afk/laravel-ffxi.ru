<?php

use App\Support\LegendLanguage;

it('detects russian text', function () {
    expect(LegendLanguage::detect('Живая легенда Санд’Ории'))->toBe(LegendLanguage::RU);
});

it('detects english text', function () {
    expect(LegendLanguage::detect('From Avatar of Flame to the Bright Falcon'))->toBe(LegendLanguage::EN);
});

it('reports mixed, empty and symbol-only texts as ambiguous', function () {
    expect(LegendLanguage::detect('Привет мир hello world'))->toBe(LegendLanguage::AMBIGUOUS)
        ->and(LegendLanguage::detect(''))->toBe(LegendLanguage::AMBIGUOUS)
        ->and(LegendLanguage::detect('   '))->toBe(LegendLanguage::AMBIGUOUS)
        ->and(LegendLanguage::detect('12345 !!! ***'))->toBe(LegendLanguage::AMBIGUOUS)
        ->and(LegendLanguage::detect(null))->toBe(LegendLanguage::AMBIGUOUS);
});

it('uses the primary locale as the fallback for an undetectable text', function () {
    expect(LegendLanguage::fallbackLocale())->toBe(LegendLanguage::RU);
});
