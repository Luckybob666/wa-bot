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
        $record = static::getModel()::create($data);
        
        // 如果传入了手机号，处理并保存
        if (isset($data['phone_numbers']) && is_array($data['phone_numbers'])) {
            $record->setPhoneNumbers($data['phone_numbers']);
            $record->save();
        }

        return $record;
    }
}
