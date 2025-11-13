<?php

namespace App\Filament\Resources\BatchComparisons\Pages;

use App\Filament\Resources\BatchComparisons\BatchComparisonResource;
use Filament\Resources\Pages\ListRecords;

class ListBatchComparisons extends ListRecords
{
    protected static string $resource = BatchComparisonResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
