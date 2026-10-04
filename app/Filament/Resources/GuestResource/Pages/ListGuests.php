<?php

namespace App\Filament\Resources\GuestResource\Pages;

use App\Filament\Resources\GuestResource;
use Filament\Resources\Pages\ListRecords;

class ListGuests extends ListRecords
{
    protected static string $resource = GuestResource::class;
}
