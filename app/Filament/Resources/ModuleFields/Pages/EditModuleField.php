<?php

namespace App\Filament\Resources\ModuleFields\Pages;

use App\Filament\Resources\ModuleFields\ModuleFieldResource;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->ensureFieldNameIsUnique(
            moduleId:  (int) $data['module_id'],
            fieldName: (string) $data['field_name'],
            ignoreId:  (int) $this->record->id,
        );

        return $data;
    }

    private function ensureFieldNameIsUnique(int $moduleId, string $fieldName, int $ignoreId): void
    {
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
