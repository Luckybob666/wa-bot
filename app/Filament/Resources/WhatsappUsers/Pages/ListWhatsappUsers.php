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
            ->with(['bot', 'group']) // 预加载机器人和群组信息
            ->where('is_active', true); // 只显示在群内的活跃用户
    }
}
