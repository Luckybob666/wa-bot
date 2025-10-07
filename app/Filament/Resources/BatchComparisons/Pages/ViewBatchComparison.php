<?php

namespace App\Filament\Resources\BatchComparisons\Pages;

use App\Filament\Resources\BatchComparisons\BatchComparisonResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBatchComparison extends ViewRecord
{
    protected static string $resource = BatchComparisonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
