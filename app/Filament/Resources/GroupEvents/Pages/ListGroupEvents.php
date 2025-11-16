<?php

namespace App\Filament\Resources\GroupEvents\Pages;

use App\Filament\Resources\GroupEvents\GroupEventResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListGroupEvents extends ListRecords
{
    protected static string $resource = GroupEventResource::class;

    // 群事件日志全部由系统自动记录，禁止手动创建
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['bot', 'group', 'whatsappUser'])
            ->orderBy('created_at', 'desc'); // 确保按时间倒序排列
    }
}
