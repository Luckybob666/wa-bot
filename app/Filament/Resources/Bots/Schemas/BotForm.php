<?php

namespace App\Filament\Resources\Bots\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use App\Models\Bot;

class BotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('机器人名称')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('请输入机器人名称，如：客服机器人1'),
                
                // 创建时隐藏，编辑时显示
                TextInput::make('phone_number')
                    ->label('WhatsApp 手机号')
                    ->tel()
                    ->maxLength(50)
                    ->placeholder('登录成功后自动获取')
                    ->disabled()
                    ->visible(fn ($record) => $record !== null),
                
                // 状态字段：创建和编辑时都不显示，由系统自动管理
                // Select::make('status') - 已移除，状态由系统自动管理
                
                DateTimePicker::make('last_seen')
                    ->label('最后活跃时间')
                    ->disabled() // 只读字段
                    ->visible(fn ($record) => $record !== null),
                
                Textarea::make('session_data')
                    ->label('会话数据')
                    ->disabled() // 只读字段
                    ->columnSpanFull()
                    ->rows(10)
                    ->placeholder('会话数据将由系统自动管理')
                    ->visible(fn ($record) => $record !== null),
            ]);
    }
}
