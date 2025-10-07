<?php

namespace App\Filament\Resources\GroupEvents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use App\Models\GroupEvent;

class GroupEventForm
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
                
                Select::make('group_id')
                    ->label('群组')
                    ->relationship('group', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('请选择群组'),
                
                Select::make('whatsapp_user_id')
                    ->label('相关用户')
                    ->relationship('whatsappUser', 'nickname')
                    ->searchable()
                    ->preload()
                    ->placeholder('请选择用户（可选）'),
                
                Select::make('event_type')
                    ->label('事件类型')
                    ->options([
                        GroupEvent::EVENT_MEMBER_JOINED => '成员加入',
                        GroupEvent::EVENT_MEMBER_LEFT => '成员离开',
                        GroupEvent::EVENT_GROUP_UPDATED => '群信息更新',
                    ])
                    ->required()
                    ->placeholder('请选择事件类型'),
                
                Textarea::make('event_data')
                    ->label('事件数据')
                    ->columnSpanFull()
                    ->rows(5)
                    ->placeholder('请输入事件详细数据（JSON 格式）')
                    ->helperText('事件相关的详细数据，通常为 JSON 格式'),
            ]);
    }
}
