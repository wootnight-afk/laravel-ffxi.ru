<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('filament.settings.save') }}
        </x-filament::button>
    </form>

    @if ($this->canManageMfa())
        <div class="mt-8 space-y-4">
            <h2 class="fi-section-header-heading text-base font-semibold text-gray-950 dark:text-white">
                {{ __('filament.settings.sections.mfa_management') }}
            </h2>

            {{ $this->table }}
        </div>
    @endif
</x-filament-panels::page>
