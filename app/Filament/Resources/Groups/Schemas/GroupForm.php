<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bot_id')
                    ->label('所属机器人')
                    ->relationship('bot', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('请选择机器人'),
                
                TextInput::make('group_id')
                    ->label('群 ID')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('请输入 WhatsApp 群 ID'),
                
                TextInput::make('name')
                    ->label('群名称')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('请输入群名称'),
                
                Textarea::make('description')
                    ->label('群描述')
                    ->columnSpanFull()
                    ->rows(3)
                    ->maxLength(500)
                    ->placeholder('请输入群描述（可选）'),
                
                TextInput::make('member_count')
                    ->label('成员数量')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->placeholder('当前群成员数量'),
                
                Select::make('phone_batch_id')
                    ->label('绑定手机号批次')
                    ->options(function () {
                        return \App\Models\PhoneBatch::where('status', 'completed')
                            ->orderBy('created_at', 'desc')
                            ->get()
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('请选择一个批次（可选）')
                    ->helperText('绑定后可以进行号码比对'),
            ]);
    }
}
