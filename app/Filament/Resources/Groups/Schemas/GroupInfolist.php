<?php

namespace App\Filament\Resources\Groups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('bot.name')
                    ->label('所属机器人'),
                TextEntry::make('group_id')
                    ->label('群 ID')
                    ->copyable()
                    ->copyMessage('群 ID 已复制'),
                TextEntry::make('name')
                    ->label('群名称'),
                TextEntry::make('description')
                    ->label('群描述')
                    ->placeholder('无描述'),
                TextEntry::make('member_count')
                    ->label('成员数量')
                    ->numeric()
                    ->badge()
                    ->color('success'),
                TextEntry::make('phoneBatch.name')
                    ->label('绑定批次')
                    ->placeholder('未绑定')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'gray'),
                TextEntry::make('auto_compare_enabled')
                    ->label('自动比对')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? '已启用' : '未启用'),
                TextEntry::make('last_sync_at')
                    ->label('最后同步')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('从未同步'),
                TextEntry::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s'),
            ]);
    }
}
