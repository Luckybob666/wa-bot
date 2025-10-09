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
            $data['processed_count'] = count($phoneNumbers); // 创建时已处理完成
            $data['status'] = 'completed'; // 创建完成即标记为已完成
        } else {
            $data['total_count'] = 0;
            $data['processed_count'] = 0;
            $data['status'] = 'pending';
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // 先创建批次记录
        $record = static::getModel()::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'total_count' => $data['total_count'],
            'processed_count' => $data['processed_count'],
            'status' => $data['status'],
        ]);
        
        // 如果有手机号，处理并保存到明细表
        if (isset($data['phone_numbers']) && is_array($data['phone_numbers'])) {
            $record->setPhoneNumbers($data['phone_numbers']);
        }
        
        return $record;
    }
}
