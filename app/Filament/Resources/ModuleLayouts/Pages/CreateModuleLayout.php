<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateModuleLayout extends CreateRecord
{
    protected static string $resource = ModuleLayoutResource::class;
    
    public function getTitle(): string 
    {
        return 'Create Layout';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureLayoutTypeIsUnique(
            moduleId:   (int) $data['module_id'],
            layoutType: (string) $data['layout_type'],
        );

        return $data;
    }

    private function ensureLayoutTypeIsUnique(int $moduleId, string $layoutType): void
    {
        $exists = ModuleLayout::where('module_id', $moduleId)
            ->where('layout_type', $layoutType)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.layout_type' => "A \"{$layoutType}\" layout already exists for this module. Each layout type can only be defined once per module.",
            ]);
        }
    }

    // Helper used by the form when populating field lists.
    public function getModuleFields(int $moduleId): array
    {
        return ModuleField::where('module_id', $moduleId)
            ->orderBy('sort_order')
            ->pluck('field_name')
            ->toArray();
    }
}
