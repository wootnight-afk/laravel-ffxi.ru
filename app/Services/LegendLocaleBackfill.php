<?php

namespace App\Services;

use App\Models\GameCharacter;
use App\Support\LegendLanguage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-off redistribution of the single `legend` column into the RU and EN
 * versions (STAGE-1 §16.2).
 *
 * The source columns are never cleared: the text is copied, so the owner can
 * verify the split and correct it by hand without losing the original. A
 * character that already has a localized version is skipped, which makes the
 * operation safe to repeat.
 */
class LegendLocaleBackfill
{
    public function __construct(
        private readonly CharacterTransaction $transaction,
        private readonly ContentRenderer $renderer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $stats = [
            'total' => 0,
            'with_legend' => 0,
            LegendLanguage::RU => 0,
            LegendLanguage::EN => 0,
            LegendLanguage::AMBIGUOUS => 0,
            'already_localized' => 0,
            'ambiguous_list' => [],
            'plan' => [],
        ];

        GameCharacter::withTrashed()->chunkById(200, function ($characters) use (&$stats): void {
            foreach ($characters as $character) {
                $stats['total']++;

                $text = (string) $character->legend;

                if (trim($text) === '') {
                    continue;
                }

                $stats['with_legend']++;

                if ($this->localized($character)) {
                    $stats['already_localized']++;

                    continue;
                }

                $locale = LegendLanguage::detect($text);

                $stats[$locale]++;

                $stats['plan'][] = [
                    'id' => $character->getKey(),
                    'name' => $character->name,
                    'locale' => $locale,
                    'column' => $this->columnFor($locale),
                    'length' => mb_strlen($text),
                ];

                if ($locale === LegendLanguage::AMBIGUOUS) {
                    $stats['ambiguous_list'][] = [
                        'id' => $character->getKey(),
                        'name' => $character->name,
                        'length' => mb_strlen($text),
                    ];
                }
            }
        });

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function apply(): array
    {
        $summary = [
            LegendLanguage::RU => 0,
            LegendLanguage::EN => 0,
            LegendLanguage::AMBIGUOUS => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        GameCharacter::withTrashed()->chunkById(200, function ($characters) use (&$summary): void {
            foreach ($characters as $character) {
                $text = (string) $character->legend;

                if (trim($text) === '' || $this->localized($character)) {
                    $summary['skipped']++;

                    continue;
                }

                $user = $character->user;

                if ($user === null) {
                    $summary['errors']++;

                    continue;
                }

                $locale = LegendLanguage::detect($text);
                $column = $this->columnFor($locale);

                try {
                    $this->transaction->run($user, function (CharacterTransaction $tx) use ($character, $text, $column): void {
                        $locked = $tx->lockCharacter((int) $character->getKey(), withTrashed: true);

                        // Re-checked under the lock: a concurrent save may have
                        // filled the localized column already.
                        if ($locked === null || $this->localized($locked)) {
                            return;
                        }

                        $locked->{$column} = $text;
                        $locked->{$column.'_html'} = $this->renderer->render($text) ?: null;
                        $locked->save();
                    });

                    $summary[$locale]++;
                } catch (Throwable $e) {
                    $summary['errors']++;

                    Log::error('Legend locale backfill failed for a character.', [
                        'character_id' => $character->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $summary;
    }

    private function columnFor(string $locale): string
    {
        return $locale === LegendLanguage::EN ? 'legend_en' : 'legend_ru';
    }

    private function localized(GameCharacter $character): bool
    {
        return trim((string) $character->legend_ru) !== ''
            || trim((string) $character->legend_en) !== '';
    }
}
