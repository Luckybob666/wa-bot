<?php

namespace App\Filament\Resources\GroupEvents\Pages;

use App\Filament\Resources\GroupEvents\GroupEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListGroupEvents extends ListRecords
{
    protected static string $resource = GroupEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['bot', 'group', 'whatsappUser'])
            ->orderBy('created_at', 'desc'); // 确保按时间倒序排列
    }
}
