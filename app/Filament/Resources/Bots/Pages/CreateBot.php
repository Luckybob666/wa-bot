<?php

namespace App\Filament\Resources\Bots\Pages;

use App\Filament\Resources\Bots\BotResource;
use App\Models\Bot;
use Filament\Resources\Pages\CreateRecord;

class CreateBot extends CreateRecord
{
    protected static string $resource = BotResource::class;
    
    /**
     * 创建记录时设置默认状态
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 设置默认状态为离线
        $data['status'] = Bot::STATUS_OFFLINE;
        
        return $data;
    }
}
