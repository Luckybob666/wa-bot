<?php

namespace App\Filament\Resources\GroupEvents\Pages;

use App\Filament\Resources\GroupEvents\GroupEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGroupEvent extends CreateRecord
{
    protected static string $resource = GroupEventResource::class;
}
