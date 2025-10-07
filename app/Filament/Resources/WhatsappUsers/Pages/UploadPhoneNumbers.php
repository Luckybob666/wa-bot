<?php

namespace App\Filament\Resources\WhatsappUsers\Pages;

use App\Filament\Resources\WhatsappUsers\WhatsappUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Models\WhatsappUser;

class UploadPhoneNumbers extends ManageRecords
{
    protected static string $resource = WhatsappUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload_phone_numbers')
                ->label('上传手机号文件')
                ->icon('heroicon-o-cloud-arrow-up')
                ->form([
                    FileUpload::make('phone_numbers_file')
                        ->label('选择文件')
                        ->acceptedFileTypes(['text/plain', '.txt'])
                        ->maxSize(1024) // 1MB
                        ->helperText('支持 .txt 格式，每行一个手机号，如：+8613800138000'),
                    
                    Textarea::make('phone_numbers_text')
                        ->label('或者直接粘贴手机号')
                        ->placeholder('每行一个手机号：\n+8613800138000\n+8613800138001\n+8613800138002')
                        ->rows(10)
                        ->helperText('每行一个手机号，以换行分隔'),
                ])
                ->action(function (array $data): void {
                    $phoneNumbers = [];
                    
                    // 验证至少有一个输入
                    if (empty($data['phone_numbers_file']) && empty($data['phone_numbers_text'])) {
                        Notification::make()
                            ->title('错误')
                            ->body('请选择文件或输入手机号')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    // 处理文件上传
                    if (!empty($data['phone_numbers_file'])) {
                        $filePath = Storage::path($data['phone_numbers_file']);
                        $content = file_get_contents($filePath);
                        $phoneNumbers = array_filter(array_map('trim', explode("\n", $content)));
                        
                        // 删除临时文件
                        Storage::delete($data['phone_numbers_file']);
                    }
                    
                    // 处理文本输入
                    if (!empty($data['phone_numbers_text'])) {
                        $textNumbers = array_filter(array_map('trim', explode("\n", $data['phone_numbers_text'])));
                        $phoneNumbers = array_merge($phoneNumbers, $textNumbers);
                    }
                    
                    // 去重
                    $phoneNumbers = array_unique($phoneNumbers);
                    
                    $imported = 0;
                    $skipped = 0;
                    
                    foreach ($phoneNumbers as $phoneNumber) {
                        // 验证手机号格式
                        if (empty($phoneNumber) || !preg_match('/^\+?[1-9]\d{1,14}$/', $phoneNumber)) {
                            $skipped++;
                            continue;
                        }
                        
                        // 检查是否已存在
                        if (WhatsappUser::where('phone_number', $phoneNumber)->exists()) {
                            $skipped++;
                            continue;
                        }
                        
                        // 创建用户
                        WhatsappUser::create([
                            'phone_number' => $phoneNumber,
                            'nickname' => null,
                            'profile_picture' => null,
                        ]);
                        
                        $imported++;
                    }
                    
                    Notification::make()
                        ->title('导入完成')
                        ->body("成功导入 {$imported} 个手机号，跳过 {$skipped} 个重复或无效号码")
                        ->success()
                        ->send();
                })
                ->modalWidth('lg'),
        ];
    }

    public function getTitle(): string
    {
        return 'WhatsApp用户管理';
    }
}
