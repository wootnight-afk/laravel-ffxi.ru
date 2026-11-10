<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Services\SitePageRegistry;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->query(null)
            ->recordAction(null)
            ->recordUrl(null)
            ->records(function (?string $search, ?array $filters, ?string $sortColumn, ?string $sortDirection): array {
                $records = app(SitePageRegistry::class)->records();
                $records = array_filter($records, function (array|Page $record) use ($search, $filters): bool {
                    if (filled($search) && ! str_contains(mb_strtolower((string) $record['title'].' '.(string) $record['slug']), mb_strtolower($search))) {
                        return false;
                    }
                    foreach (['is_published', 'show_in_menu'] as $key) {
                        $value = $filters[$key]['value'] ?? null;
                        if ($value !== null && $value !== '' && (! $record instanceof Page || (bool) $record[$key] !== (bool) $value)) {
                            return false;
                        }
                    }

                    return true;
                });
                if ($sortColumn !== null) {
                    usort($records, fn (array|Page $a, array|Page $b): int => ($sortDirection === 'desc' ? -1 : 1)
                        * (($a[$sortColumn] ?? '') <=> ($b[$sortColumn] ?? '')));
                }

                return array_values($records);
            })
            ->paginated(false);
    }

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
