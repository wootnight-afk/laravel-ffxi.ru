<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button type="submit">
                {{ __('filament.page_editor.save') }}
            </x-filament::button>

            <x-filament::button
                tag="a"
                color="gray"
                :href="\App\Filament\Resources\PageResource::getUrl('index')"
            >
                {{ __('filament.page_editor.back') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
