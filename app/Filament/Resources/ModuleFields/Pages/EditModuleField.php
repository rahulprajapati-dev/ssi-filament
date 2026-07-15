<?php

namespace App\Filament\Resources\ModuleFields\Pages;

use App\Filament\Resources\ModuleFields\ModuleFieldResource;
use App\Helpers\Studio\FieldTypeMap;
use App\Models\ModuleField;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditModuleField extends EditRecord
{
    protected static string $resource = ModuleFieldResource::class;
    public function getTitle(): string 
    {
        return 'Edit Field';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['type'] ?? '') === 'relationship') {
            $config = is_array($data['options']) ? ($data['options'][0] ?? []) : [];
            $data['relate_module'] = $config['relate_module'] ?? '';
            $data['display_field'] = $config['display_field'] ?? 'name';
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->ensureFieldNameIsUnique(
            moduleId:  (int) $data['module_id'],
            fieldName: (string) $data['field_name'],
            ignoreId:  (int) $this->record->id,
        );

        if (($data['type'] ?? '') === 'relationship') {
            $data['options'] = [[
                'relate_module' => strtolower($data['relate_module'] ?? ''),
            ]];
        }
        unset($data['relate_module'], $data['display_field']);

        return $data;
    }

    private function ensureFieldNameIsUnique(int $moduleId, string $fieldName, int $ignoreId): void
    {
        if (in_array(strtolower($fieldName), FieldTypeMap::SYSTEM_FIELD_NAMES, true)) {
            throw ValidationException::withMessages([
                'data.field_name' => "\"{$fieldName}\" is a reserved system field and cannot be added manually.",
            ]);
        }

        $exists = ModuleField::where('module_id', $moduleId)
            ->whereRaw('LOWER(field_name) = ?', [strtolower($fieldName)])
            ->where('id', '!=', $ignoreId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.field_name' => "A field named \"{$fieldName}\" already exists in this module.",
            ]);
        }
    }
}
