<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\GroupResource;
use Filament\Resources\Pages\ListRecords;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    // 群组完全由机器人同步创建，不允许在后台手动创建群组
    protected function getHeaderActions(): array
    {
        return [];
    }
}
