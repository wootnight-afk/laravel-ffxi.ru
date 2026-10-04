<x-filament-widgets::widget>
    <x-filament::section heading="Последние административные действия">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-3 py-2 font-medium">Дата</th>
                        <th class="px-3 py-2 font-medium">Пользователь</th>
                        <th class="px-3 py-2 font-medium">Действие</th>
                        <th class="px-3 py-2 font-medium">Объект</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2">{{ $entry->created_at?->translatedFormat('d.m.Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $entry->user?->name ?? 'Система' }}</td>
                            <td class="px-3 py-2">{{ $entry->action }}</td>
                            <td class="px-3 py-2">{{ $entry->subject_type ? class_basename($entry->subject_type) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-center text-gray-500" colspan="4">
                                Записей пока нет
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
