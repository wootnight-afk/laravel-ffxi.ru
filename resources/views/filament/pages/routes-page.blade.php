@php
    $routes = $this->getRoutesProperty();
    $labels = $this->getCategoryLabels();
    $badge = [
        'public' => 'success',
        'auth' => 'info',
        'filament' => 'warning',
        'api' => 'primary',
        'technical' => 'gray',
        'write' => 'gray',
        'missing' => 'danger',
        'unclassified' => 'danger',
    ];
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament.routes_page.heading') }}
        </x-slot>
        <x-slot name="description">
            {{ __('filament.routes_page.description') }}
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 text-left font-medium">{{ __('filament.routes_page.columns.methods') }}</th>
                        <th class="px-3 py-2 text-left font-medium">{{ __('filament.routes_page.columns.uri') }}</th>
                        <th class="px-3 py-2 text-left font-medium">{{ __('filament.routes_page.columns.name') }}</th>
                        <th class="px-3 py-2 text-left font-medium">{{ __('filament.routes_page.columns.category') }}</th>
                        <th class="px-3 py-2 text-left font-medium">{{ __('filament.routes_page.columns.reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($routes as $route)
                        <tr class="border-b border-gray-100 align-top dark:border-white/5">
                            <td class="px-3 py-2 whitespace-nowrap text-gray-500">
                                {{ implode('/', array_diff($route['methods'], ['HEAD'])) }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <code>/{{ $route['uri'] }}</code>
                            </td>
                            <td class="px-3 py-2 text-gray-500">
                                {{ $route['name'] ?? '—' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <x-filament::badge :color="$badge[$route['category']] ?? 'gray'">
                                    {{ $labels[$route['category']] ?? $route['category'] }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-2 text-gray-500">
                                {{ $route['reason'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
