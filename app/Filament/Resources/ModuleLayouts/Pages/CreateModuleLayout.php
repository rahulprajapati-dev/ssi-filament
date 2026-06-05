<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use App\Models\ModuleField;
use Filament\Resources\Pages\CreateRecord;

class CreateModuleLayout extends CreateRecord
{
    protected static string $resource = ModuleLayoutResource::class;

    public function getModuleFields(int $moduleId): array
    {
        return ModuleField::where('module_id', $moduleId)
            ->orderBy('sort_order')
            ->pluck('field_name')
            ->toArray();
    }
}
