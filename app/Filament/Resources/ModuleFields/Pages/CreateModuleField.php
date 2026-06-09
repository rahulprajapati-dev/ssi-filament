<?php

namespace App\Filament\Resources\ModuleFields\Pages;

use App\Filament\Resources\ModuleFields\ModuleFieldResource;
use App\Models\ModuleField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateModuleField extends CreateRecord
{
    protected static string $resource = ModuleFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureFieldNameIsUnique(
            moduleId:  (int) $data['module_id'],
            fieldName: (string) $data['field_name'],
        );

        return $data;
    }

    private function ensureFieldNameIsUnique(int $moduleId, string $fieldName): void
    {
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
