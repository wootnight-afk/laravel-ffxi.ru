<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\PageResource\Pages\EditPage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNews extends ListRecords
{
    protected static string $resource = NewsResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('editNewsPage')
                ->label(__('filament.news_page.action'))
                ->icon('heroicon-o-document-text')
                ->url(fn (): string => EditPage::getUrl(['record' => 'sys.news.index']))
                // The editor is admin-only for system pages, so the
                // action is hidden for editors instead of leading to a 403.
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            CreateAction::make(),
        ];
    }
}
