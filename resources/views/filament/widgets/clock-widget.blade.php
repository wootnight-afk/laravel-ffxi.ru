{{--
    Dashboard world clocks (ADR-011 §2.25): UTC, device, region.

    The first render is server-side; Alpine refreshes every face once per
    second with Intl. Offsets are recomputed on each tick, so DST transitions
    stay correct while the page is open. The region face is keyed by its
    timezone, so choosing another zone re-initialises Alpine with it.

    The 12/24-hour format toggle lives in the section header (afterHeader slot).
    Format is stored in a plain cookie (clock_format, excluded from encryption,
    same pattern as `tz`). Every face keeps its own format and re-ticks on the
    `clock-format` window event broadcast by the toggle.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('filament.dashboard.clock.heading')">
        <x-slot name="afterHeader">
            {{-- The toggle owns its copy of the format so it can highlight the
                 active button, and broadcasts changes to every clock face. --}}
            <div
                role="group"
                aria-label="{{ __('filament.dashboard.clock.format_label') }}"
                style="display:inline-flex; gap:2px; align-items:center;"
                x-data="{
                    format: @js($timeFormat),
                    setFormat(v) {
                        if (v === this.format) return;
                        this.format = v;
                        document.cookie = 'clock_format=' + v + '; path=/; max-age=31536000; SameSite=Lax';
                        this.$dispatch('clock-format', v);
                    },
                }"
            >
                {{-- Values are listed as strings: array keys '24' / '12' would be
                     cast to int by PHP and break the strict comparison below. --}}
                @foreach (['24', '12'] as $value)
                    <button
                        type="button"
                        aria-pressed="{{ $timeFormat === $value ? 'true' : 'false' }}"
                        x-on:click="setFormat('{{ $value }}')"
                        x-bind:aria-pressed="(format === '{{ $value }}').toString()"
                        x-bind:style="format === '{{ $value }}'
                            ? {background:'var(--primary-600)',color:'#fff',borderColor:'var(--primary-600)',opacity:'1'}
                            : {background:'transparent',color:'inherit',borderColor:'var(--gray-300)',opacity:'0.65'}"
                        style="padding:2px 8px;border-radius:6px;border:1px solid var(--gray-300);font-size:11px;font-weight:500;line-height:1.4;cursor:pointer;transition:background 75ms,color 75ms,border-color 75ms,opacity 75ms;"
                    >{{ __("filament.dashboard.clock.format_{$value}") }}</button>
                @endforeach
            </div>
        </x-slot>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            @foreach ($clocks as $clock)
                @php
                    $isDevice = $clock['is_device'];
                    $isRegion = $clock['is_region'];
                    $showFace = ! $isRegion || $clock['selected'];
                    $fromCookie = $clock['from_cookie'] ?? false;
                    $wireKey = $isRegion
                        ? "clock-region-{$clock['timezone']}-".(int) $clock['selected']
                        : "clock-{$clock['key']}";
                @endphp

                <div
                    wire:key="{{ $wireKey }}"
                    class="flex flex-col gap-1"
                    @if ($showFace)
                        x-data="{
                            timezone: @js($clock['timezone']),
                            time: @js($clock['time']),
                            offset: @js($clock['offset']),
                            format: @js($timeFormat),
                            detected: false,
                            fromCookie: @js($fromCookie),
                            tick() {
                                try {
                                    const now = new Date();

                                    this.time = new Intl.DateTimeFormat('en-US', {
                                        timeZone: this.timezone,
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                        hourCycle: this.format === '12' ? 'h12' : 'h23',
                                    }).format(now);

                                    const name = new Intl.DateTimeFormat('en-GB', {
                                        timeZone: this.timezone,
                                        timeZoneName: 'longOffset',
                                    }).formatToParts(now).find((part) => part.type === 'timeZoneName')?.value ?? '';

                                    this.offset = 'UTC' + (name.replace('GMT', '') || '+00:00');
                                } catch (e) {
                                    // Unknown zone — keep the last rendered values.
                                }
                            },
                            init() {
                                @if ($isDevice)
                                try {
                                    const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;

                                    if (detected) {
                                        this.timezone = detected;
                                        this.detected = true;
                                    }
                                } catch (e) {
                                    // Intl unavailable — the server-rendered fallback stays.
                                }
                                @endif

                                this.tick();

                                setInterval(() => this.tick(), 1000);
                            },
                        }"
                        x-on:clock-format.window="format = $event.detail; tick()"
                    @endif
                >
                    @if ($showFace)
                        <span class="text-3xl font-semibold tabular-nums" x-text="time">{{ $clock['time'] }}</span>
                    @else
                        <span class="text-3xl font-semibold tabular-nums text-gray-400 dark:text-gray-500">--:--:--</span>
                    @endif

                    <span class="text-sm font-medium">{{ $clock['label'] }}</span>

                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($isRegion && ! $clock['selected'])
                            {{ __('filament.dashboard.clock.region_none') }}
                        @else
                            <span @if ($isDevice) x-text="timezone" @endif>{{ $clock['timezone'] }}</span>
                            ·
                            <span @if ($showFace) x-text="offset" @endif>{{ $clock['offset'] }}</span>
                            @if ($isDevice)
                                <span x-cloak x-show="! detected && ! fromCookie">
                                    ({{ __('filament.dashboard.clock.device_fallback') }})
                                </span>
                            @endif
                        @endif
                    </span>

                    @if ($isRegion)
                        <div class="mt-3 w-full max-w-xs" wire:key="clock-region-form">
                            {{ $this->form }}

                            @if ($clock['selected'])
                                <x-filament::button
                                    wire:click="clearRegion"
                                    color="gray"
                                    size="xs"
                                    type="button"
                                >
                                    {{ __('filament.dashboard.clock.region_clear') }}
                                </x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
