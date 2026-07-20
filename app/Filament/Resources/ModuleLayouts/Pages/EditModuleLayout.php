<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\Hooks\ModuleLayoutHooks;
use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use App\Helpers\Studio\FieldTypeMap;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditModuleLayout extends EditRecord
{
    protected static string $resource = ModuleLayoutResource::class;

    public function getTitle(): string
    {
        return 'Edit Layout';
    }

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
            moduleId:   (int) ($data['module_id'] ?? $this->record->module_id),
            layoutType: (string) $data['layout_type'],
            ignoreId:   (int) $this->record->id,
        );

        return app(ModuleLayoutHooks::class)->applyLayoutInheritance($data, $this->record);
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
    public function getModuleFields(int $moduleId, ?string $layoutType = null): array
    {
        $layoutType = $layoutType ?? $this->record?->layout_type;

        $excludeSystem = in_array($layoutType, ['create', 'edit'], true);

        $query = ModuleField::where('module_id', $moduleId)->orderBy('sort_order');

        if ($excludeSystem) {
            $query->whereNotIn('field_name', FieldTypeMap::SYSTEM_FIELD_NAMES);
        }

        return $query->get(['field_name', 'label'])
            ->map(fn ($f) => ['field_name' => $f->field_name, 'label' => $f->label ?: $f->field_name])
            ->toArray();
    }
}
