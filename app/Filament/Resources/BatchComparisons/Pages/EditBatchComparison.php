<?php

namespace App\Filament\Resources\BatchComparisons\Pages;

use App\Filament\Resources\BatchComparisons\BatchComparisonResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBatchComparison extends EditRecord
{
    protected static string $resource = BatchComparisonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
