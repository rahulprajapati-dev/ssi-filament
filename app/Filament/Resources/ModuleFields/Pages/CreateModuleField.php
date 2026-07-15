<?php

namespace App\Filament\Resources\ModuleFields\Pages;

use App\Filament\Resources\ModuleFields\ModuleFieldResource;
use App\Helpers\Studio\FieldTypeMap;
use App\Models\ModuleField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateModuleField extends CreateRecord
{
    protected static string $resource = ModuleFieldResource::class;
    public function getTitle(): string 
    {
        return 'Create Field';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? '') === 'relationship'
            && ! str_ends_with($data['field_name'] ?? '', '_id')) {
            $data['field_name'] .= '_id';
        }

        $this->ensureFieldNameIsUnique(
            moduleId:  (int) $data['module_id'],
            fieldName: (string) $data['field_name'],
        );

        if (($data['type'] ?? '') === 'relationship') {
            $data['options'] = [[
                'relate_module' => strtolower($data['relate_module'] ?? ''),
            ]];
        }
        unset($data['relate_module'], $data['display_field']);

        return $data;
    }

    private function ensureFieldNameIsUnique(int $moduleId, string $fieldName): void
    {
        if (in_array(strtolower($fieldName), FieldTypeMap::SYSTEM_FIELD_NAMES, true)) {
            throw ValidationException::withMessages([
                'data.field_name' => "\"{$fieldName}\" is a reserved system field and cannot be added manually.",
            ]);
        }

        $exists = ModuleField::where('module_id', $moduleId)
            ->whereRaw('LOWER(field_name) = ?', [strtolower($fieldName)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.field_name' => "A field named \"{$fieldName}\" already exists in this module.",
            ]);
        }
    }
}
