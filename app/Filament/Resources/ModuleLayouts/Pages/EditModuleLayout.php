<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditModuleLayout extends EditRecord
{
    protected static string $resource = ModuleLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->ensureLayoutTypeIsUnique(
            moduleId:   (int) $data['module_id'],
            layoutType: (string) $data['layout_type'],
            ignoreId:   (int) $this->record->id,
        );

        return $data;
    }

    private function ensureLayoutTypeIsUnique(int $moduleId, string $layoutType, int $ignoreId): void
    {
        $exists = ModuleLayout::where('module_id', $moduleId)
            ->where('layout_type', $layoutType)
            ->where('id', '!=', $ignoreId)
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
