<?php

namespace App\Filament\Resources\WhatsappUsers\Pages;

use App\Filament\Resources\WhatsappUsers\WhatsappUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsappUser extends CreateRecord
{
    protected static string $resource = WhatsappUserResource::class;
}
