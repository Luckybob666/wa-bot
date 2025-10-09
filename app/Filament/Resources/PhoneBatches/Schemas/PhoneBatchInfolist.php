<?php

namespace App\Filament\Resources\PhoneBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PhoneBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('批次名称'),
                TextEntry::make('description')
                    ->label('批次描述'),
                TextEntry::make('total_count')
                    ->label('总数量')
                    ->numeric(),
                TextEntry::make('processed_count')
                    ->label('已处理数量')
                    ->numeric(),
                TextEntry::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                    }),
                TextEntry::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s'),
            ]);
    }
}
