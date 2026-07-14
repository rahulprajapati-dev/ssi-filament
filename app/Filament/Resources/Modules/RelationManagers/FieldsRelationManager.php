<?php

namespace App\Filament\Resources\Modules\RelationManagers;

use App\Helpers\JsonStudioFormBuilder;
use App\Helpers\JsonTableBuilder;
use App\Models\ModuleField;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Fields';

    public function form(Schema $schema): Schema
    {
        $op = $schema->getOperation();
        $file = match ($op) {
            'edit' => __DIR__ . '/fields_form.json',
            'view' => __DIR__ . '/fields_form_detail_view.json',
            default => __DIR__ . '/fields_form.json',
        };
        $config = json_decode(file_get_contents($file), true);
        return JsonStudioFormBuilder::buildSchema($schema, $config);
    }

    public function table(Table $table): Table
    {
        $config = json_decode(file_get_contents(__DIR__ . '/fields_table.json'), true);
        return JsonTableBuilder::build($table, $config);
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $moduleId = $this->getOwnerRecord()->id;
        $exists = ModuleField::where('module_id', $moduleId)->where('field_name', $data['field_name'] ?? '')->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'data.field_name' => 'A field with this name already exists in this module.',
            ]);
        }

        if (empty($data['sort_order'])) {
            $data['sort_order'] = (ModuleField::where('module_id', $moduleId)->max('sort_order') ?? 0) + 1;
        }

        if (($data['type'] ?? '') === 'relationship') {
            $data['options'] = [[
                'relate_module' => strtolower($data['relate_module'] ?? ''),
                'display_field' => $data['display_field'] ?: 'name',
            ]];
        }
        unset($data['relate_module'], $data['display_field']);

        $data['module_id'] = $moduleId;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $moduleId = $this->getOwnerRecord()->id;
        $recordId = $this->getMountedTableActionRecord()?->id;

        $exists = ModuleField::where('module_id', $moduleId)
            ->where('field_name', $data['field_name'] ?? '')
            ->when($recordId, fn ($q) => $q->where('id', '!=', $recordId))->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.field_name' => 'A field with this name already exists in this module.',
            ]);
        }

        if (($data['type'] ?? '') === 'relationship') {
            $data['options'] = [[
                'relate_module' => strtolower($data['relate_module'] ?? ''),
                'display_field' => $data['display_field'] ?: 'name',
            ]];
        }
        unset($data['relate_module'], $data['display_field']);

        return $data;
    }
}
