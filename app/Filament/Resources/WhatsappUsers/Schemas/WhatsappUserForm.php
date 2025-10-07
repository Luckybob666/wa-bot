<?php

namespace App\Filament\Resources\WhatsappUsers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class WhatsappUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone_number')
                    ->label('手机号')
                    ->tel()
                    ->required()
                    ->maxLength(50)
                    ->placeholder('请输入 WhatsApp 手机号，如：+8613800138000'),
                
                TextInput::make('nickname')
                    ->label('昵称')
                    ->maxLength(255)
                    ->placeholder('请输入用户昵称（可选）'),
                
                FileUpload::make('profile_picture')
                    ->label('头像')
                    ->image()
                    ->directory('whatsapp-users/avatars')
                    ->visibility('public')
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '1:1',
                    ])
                    ->placeholder('点击上传头像')
                    ->helperText('支持 JPG、PNG 格式，建议尺寸 200x200 像素'),
            ]);
    }
}
