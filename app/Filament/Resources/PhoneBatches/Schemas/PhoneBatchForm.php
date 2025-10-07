<?php

namespace App\Filament\Resources\PhoneBatches\Schemas;

use App\Models\PhoneBatch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PhoneBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('批次名称')
                    ->placeholder('例如：营销客户列表_2024-01')
                    ->required(),
                
                Textarea::make('description')
                    ->label('批次描述')
                    ->placeholder('描述这批手机号的来源或用途')
                    ->columnSpanFull(),
                
                
                Textarea::make('phone_numbers')
                    ->label('手机号列表')
                    ->placeholder('每行输入一个手机号，例如：' . PHP_EOL . '13800138000' . PHP_EOL . '13900139000' . PHP_EOL . '13700137000')
                    ->helperText('每行一个手机号，系统会自动统计数量')
                    ->columnSpanFull()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $phoneNumbers = array_filter(array_map('trim', explode("\n", $state)));
                            $set('total_count', count($phoneNumbers));
                        }
                    }),
                
                TextInput::make('total_count')
                    ->label('总数量')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->helperText('系统自动统计'),
                
                TextInput::make('processed_count')
                    ->label('已处理数量')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->helperText('系统自动更新'),
                
                Select::make('status')
                    ->label('状态')
                    ->options(PhoneBatch::getStatusOptions())
                    ->default(PhoneBatch::STATUS_PENDING)
                    ->disabled()
                    ->helperText('系统自动管理'),
            ]);
    }
}
