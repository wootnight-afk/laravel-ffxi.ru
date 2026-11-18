<?php

namespace App\Services;

use App\Models\User;
use App\Support\LegendLanguage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-off redistribution of `users.legend` into the RU and EN profile
 * descriptions (STAGE-1 §17.2).
 *
 * The source column is copied, never cleared: a wrong guess can be corrected
 * by hand without losing anything. Undetectable texts are moved to the primary
 * locale unchanged and listed in the report for the owner to review.
 */
class ProfileLegendLocaleBackfill
{
    public function __construct(private readonly ContentRenderer $renderer) {}

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

        User::withTrashed()->chunkById(200, function ($users) use (&$stats): void {
            foreach ($users as $user) {
                $stats['total']++;

                $text = (string) $user->legend;

                if (trim($text) === '') {
                    continue;
                }

                $stats['with_legend']++;

                if ($this->localized($user)) {
                    $stats['already_localized']++;

                    continue;
                }

                $locale = LegendLanguage::detect($text);
                $column = $locale === LegendLanguage::EN ? 'legend_en' : 'legend_ru';

                $stats[$locale]++;
                $stats['plan'][] = [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $locale,
                    'column' => $column,
                    'length' => mb_strlen($text),
                ];

                if ($locale === LegendLanguage::AMBIGUOUS) {
                    $stats['ambiguous_list'][] = $user->getKey();
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

        User::withTrashed()->chunkById(200, function ($users) use (&$summary): void {
            foreach ($users as $user) {
                $text = (string) $user->legend;

                if (trim($text) === '' || $this->localized($user)) {
                    $summary['skipped']++;

                    continue;
                }

                $locale = LegendLanguage::detect($text);
                $column = $locale === LegendLanguage::EN ? 'legend_en' : 'legend_ru';

                try {
                    $user->{$column} = $text;
                    $user->{$column.'_html'} = $this->renderer->render($text);
                    $user->save();

                    $summary[$locale]++;
                } catch (Throwable $e) {
                    $summary['errors']++;
                    Log::error('Profile description backfill failed for an account.', [
                        'user_id' => $user->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $summary;
    }

    private function localized(User $user): bool
    {
        return trim((string) $user->legend_ru) !== ''
            || trim((string) $user->legend_en) !== '';
    }
}
