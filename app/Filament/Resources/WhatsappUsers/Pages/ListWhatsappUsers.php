<?php

namespace App\Filament\Resources\WhatsappUsers\Pages;

use App\Filament\Resources\WhatsappUsers\WhatsappUserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsappUsers extends ListRecords
{
    protected static string $resource = WhatsappUserResource::class;

    // WhatsApp 用户列表由机器人同步生成，禁止手动创建
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['bot', 'group']) // 预加载机器人和群组信息
            ->where('is_active', true); // 只显示在群内的活跃用户
    }
}
