<?php

namespace App\Filament\Resources\PhoneBatches\Pages;

use App\Filament\Resources\PhoneBatches\PhoneBatchResource;
use App\Models\Group;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPhoneBatch extends EditRecord
{
    protected static string $resource = PhoneBatchResource::class;
    
    /**
     * 临时存储要更新的手机号列表
     */
    protected array $phoneNumbersToUpdate = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 加载现有的手机号列表到表单
        $record = $this->record;
        if ($record && $record->exists) {
            $phoneNumbers = $record->getPhoneNumbers();
            $data['phone_numbers'] = implode("\n", $phoneNumbers);
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 处理手机号列表（保存到临时变量，稍后在 afterSave 中处理）
        if (isset($data['phone_numbers']) && !empty($data['phone_numbers'])) {
            $phoneNumbers = array_filter(array_map('trim', explode("\n", $data['phone_numbers'])));
            // 将手机号列表保存到临时属性，以便在 afterSave 中使用
            $this->phoneNumbersToUpdate = $phoneNumbers;
            $data['total_count'] = count($phoneNumbers);
            $data['processed_count'] = count($phoneNumbers); // 更新时已处理完成
            $data['status'] = 'completed'; // 更新完成即标记为已完成
        } else {
            $this->phoneNumbersToUpdate = [];
            $data['total_count'] = 0;
            $data['processed_count'] = 0;
            $data['status'] = 'pending';
        }
        
        // 移除 phone_numbers 字段，因为它不在 fillable 中
        unset($data['phone_numbers']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        
        // 更新手机号列表
        if (!empty($this->phoneNumbersToUpdate)) {
            $record->setPhoneNumbers($this->phoneNumbersToUpdate);
            // 刷新记录以获取最新数据
            $record->refresh();
        }
        
        // 查找所有绑定了该批次的群组，并重新计算比对结果
        $groups = Group::where('phone_batch_id', $record->id)->get();
        $updatedCount = 0;
        
        foreach ($groups as $group) {
            // 确保群组关系已加载
            $group->load('phoneBatch');
            $group->updateBatchComparison();
            $updatedCount++;
        }
        
        // 显示通知
        if ($updatedCount > 0) {
            Notification::make()
                ->title('更新成功')
                ->body("批次已更新，已重新计算 {$updatedCount} 个绑定群组的比对结果")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('更新成功')
                ->body('批次已更新')
                ->success()
                ->send();
        }
    }
}
