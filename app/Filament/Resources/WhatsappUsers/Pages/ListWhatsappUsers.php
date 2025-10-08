<?php

namespace App\Filament\Resources\WhatsappUsers\Pages;

use App\Filament\Resources\WhatsappUsers\WhatsappUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsappUsers extends ListRecords
{
    protected static string $resource = WhatsappUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['groups.bot']); // 预加载群组和机器人信息
    }
}
