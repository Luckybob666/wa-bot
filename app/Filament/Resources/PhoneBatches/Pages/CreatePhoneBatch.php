<?php

namespace App\Filament\Resources\PhoneBatches\Pages;

use App\Filament\Resources\PhoneBatches\PhoneBatchResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePhoneBatch extends CreateRecord
{
    protected static string $resource = PhoneBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 处理手机号列表
        if (isset($data['phone_numbers']) && !empty($data['phone_numbers'])) {
            $phoneNumbers = array_filter(array_map('trim', explode("\n", $data['phone_numbers'])));
            $data['phone_numbers'] = $phoneNumbers;
            $data['total_count'] = count($phoneNumbers);
        }

        // 设置默认状态
        $data['status'] = 'pending';
        $data['processed_count'] = 0;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // 直接创建记录，数据已经在 mutateFormDataBeforeCreate 中处理过了
        $record = static::getModel()::create($data);
        
        return $record;
    }
}
