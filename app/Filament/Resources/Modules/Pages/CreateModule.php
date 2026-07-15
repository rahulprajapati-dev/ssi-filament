<?php

namespace App\Filament\Resources\Modules\Pages;

use App\Filament\Resources\Modules\ModuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModule extends CreateRecord
{
    protected static string $resource = ModuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key']  = strtolower(trim($data['key'] ?? ''));
        $data['name'] = strtolower(trim($data['name'] ?? ''));
        return $data;
    }
}
