<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\TimezoneOptions;
use App\Services\TimezoneResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

/**
 * Dashboard "World time" group: three independent clocks (ADR-011 §2.25).
 *
 * 1. UTC      — always UTC, independent of every user/device preference.
 * 2. Device   — the browser timezone via Intl on the client; the server-side
 *               first render falls back to the `tz` cookie (TimezoneResolver),
 *               then to UTC. Never derived from users.timezone.
 * 3. Region   — the IANA zone stored in users.secondary_timezone, chosen inline
 *               in this widget. NULL shows an explicit "not selected" state.
 *
 * The first render is server-side; Alpine refreshes each face every second with
 * Intl, so there is no Livewire polling. UTC offsets are recomputed on every
 * tick (server and client) so seasonal DST changes stay correct — they are
 * never stored.
 *
 * The `$form` property is resolved by Livewire's dynamic property handling from
 * the `form()` schema definition (Filament Schemas).
 *
 * @property-read Schema $form
 */
class ClockWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.widgets.clock-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    /**
     * The first render must be server-side (ADR-011 §2.25): a lazy widget
     * would show a placeholder until the Livewire round trip completes.
     */
    protected static bool $isLazy = false;

    /**
     * Cookie name for the 12/24-hour format preference (plain value, excluded
     * from encryption). Mirrors the `tz` cookie pattern (ADR-011 §9 L2): the
     * toggle is driven by Alpine, so no column and no Livewire round trip.
     */
    public const FORMAT_COOKIE = 'clock_format';

    /**
     * Form state for the region selector.
     *
     * @var array{secondary_timezone?: string|null}|null
     */
    public ?array $data = [];

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $this->form->fill([
            'secondary_timezone' => $this->selectedRegion(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('secondary_timezone')
                    ->label(__('filament.dashboard.clock.region'))
                    ->placeholder(__('filament.dashboard.clock.region_none'))
                    ->helperText(__('filament.dashboard.clock.region_help'))
                    ->options(fn (): array => TimezoneOptions::grouped())
                    // Filament renders at most 50 options by default, which
                    // truncated the catalogue to the first African zones.
                    ->optionsLimit(TimezoneOptions::count() + 1)
                    ->searchable()
                    // Server-side search over the full catalogue: matches the
                    // exact IANA identifier and the readable city name
                    // (underscores and spaces are equivalent).
                    ->getSearchResultsUsing(fn (string $search): array => TimezoneOptions::search($search))
                    ->live()
                    ->rules(['nullable', 'string', 'max:64', 'timezone'])
                    ->validationMessages(['timezone' => __('filament.dashboard.clock.region_invalid')])
                    ->afterStateUpdated(fn () => $this->saveRegion()),
            ]);
    }

    /**
     * Clear the region selection and persist the change.
     */
    public function clearRegion(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $this->data['secondary_timezone'] = null;
        $user->secondary_timezone = null;
        $user->save();
    }

    public function render(): View
    {
        return view($this->view, [
            'clocks' => $this->clocks(),
            'timeFormat' => $this->timeFormat(),
        ]);
    }

    /**
     * Persist the region choice for the current user only. Filament runs the
     * form rules through getState(); an invalid zone throws and nothing is
     * written.
     */
    private function saveRegion(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $state = $this->form->getState();

        $user->secondary_timezone = $state['secondary_timezone'] ?? null;
        $user->save();
    }

    /**
     * The stored region for the current user, validated with the shared
     * TimezoneOptions service. Invalid or missing values return null, so a
     * stale identifier never reaches the form or the clock face.
     */
    private function selectedRegion(): ?string
    {
        $region = auth()->user()?->secondary_timezone;

        return is_string($region) && TimezoneOptions::isValid($region) ? $region : null;
    }

    /**
     * Read the time-format cookie. Returns '24' (default) or '12'.
     * Any other value is treated as '24' for graceful degradation.
     */
    private function timeFormat(): string
    {
        $value = request()->cookie(self::FORMAT_COOKIE);

        return $value === '12' ? '12' : '24';
    }

    /**
     * Build the three clock faces. The device offset is a server-side guess
     * (from the `tz` cookie); Alpine replaces it with the exact Intl value.
     *
     * @return array<int, array<string, mixed>>
     */
    private function clocks(): array
    {
        $browser = app(TimezoneResolver::class)->browserTimezone();

        $region = $this->selectedRegion();

        return [
            $this->face('utc', __('filament.dashboard.clock.utc'), 'UTC', false, false),
            [
                ...$this->face('device', __('filament.dashboard.clock.device'), $browser ?? 'UTC', true, false),
                'from_cookie' => $browser !== null,
            ],
            $this->face(
                'region',
                __('filament.dashboard.clock.region'),
                $region ?? 'UTC',
                false,
                true,
                $region !== null,
            ),
        ];
    }

    /**
     * @return array{key: string, label: string, timezone: string, time: string, offset: string, is_device: bool, is_region: bool, selected: bool}
     */
    private function face(string $key, string $label, string $timezone, bool $isDevice, bool $isRegion, bool $selected = true): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'timezone' => $timezone,
            'time' => now()->setTimezone($timezone)->format($this->phpTimeFormat()),
            'offset' => 'UTC'.TimezoneOptions::offset($timezone),
            'is_device' => $isDevice,
            'is_region' => $isRegion,
            'selected' => $selected,
        ];
    }

    /**
     * Server-side time format string matching the client hourCycle.
     */
    private function phpTimeFormat(): string
    {
        return $this->timeFormat() === '12' ? 'h:i:s A' : 'H:i:s';
    }
}
