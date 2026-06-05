<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use App\Models\ModuleField;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditModuleLayout extends EditRecord
{
    protected static string $resource = ModuleLayoutResource::class;

    public function getModuleFields(int $moduleId): array
    {
        return ModuleField::where('module_id', $moduleId)
            ->orderBy('sort_order')
            ->pluck('field_name')
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
