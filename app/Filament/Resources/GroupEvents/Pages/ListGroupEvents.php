<?php

namespace App\Filament\Resources\GroupEvents\Pages;

use App\Filament\Resources\GroupEvents\GroupEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGroupEvents extends ListRecords
{
    protected static string $resource = GroupEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
