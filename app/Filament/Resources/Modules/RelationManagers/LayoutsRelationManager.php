<?php

namespace App\Filament\Resources\Modules\RelationManagers;

use App\Helpers\JsonStudioFormBuilder;
use App\Helpers\JsonTableBuilder;
use App\Helpers\Studio\FieldTypeMap;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class LayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'layouts';

    protected static ?string $title = 'Layouts';

    public function form(Schema $schema): Schema
    {
        $config = json_decode(file_get_contents(__DIR__ . '/layouts_form.json'), true);

        $moduleId = $this->getOwnerRecord()->id;

        $fields = ModuleField::where('module_id', $moduleId)
            ->whereNotIn('field_name', FieldTypeMap::SYSTEM_FIELD_NAMES)
            ->orderBy('sort_order')
            ->get(['field_name', 'label'])
            ->map(fn ($f) => ['field_name' => $f->field_name, 'label' => $f->label ?: $f->field_name])
            ->toArray();

        $config['components'] = $this->injectOwnerData($config['components'], $moduleId, $fields);

        return JsonStudioFormBuilder::buildSchema($schema, $config);
    }

    private function injectOwnerData(array $components, int $moduleId, array $fields): array
    {
        foreach ($components as &$item) {
            if (($item['name'] ?? null) === 'module_id') {
                $item['default'] = $moduleId;
                $item['value'] = $moduleId;
            }
            if (($item['component'] ?? null) === 'dragDrop') {
                $item['owner_module_id']     = $moduleId;
                $item['owner_module_fields'] = $fields;
            }
            foreach (['schema', 'items'] as $key) {
                if (! empty($item[$key]) && is_array($item[$key])) {
                    $item[$key] = $this->injectOwnerData($item[$key], $moduleId, $fields);
                }
            }
        }
        return $components;
    }

    // Called by the drag-drop blade component via Livewire when layout_type changes.
    public function getModuleFields(int $moduleId, ?string $layoutType = null): array
    {
        return ModuleField::where('module_id', $moduleId)
            ->whereNotIn('field_name', FieldTypeMap::SYSTEM_FIELD_NAMES)
            ->orderBy('sort_order')
            ->get(['field_name', 'label'])
            ->map(fn ($f) => ['field_name' => $f->field_name, 'label' => $f->label ?: $f->field_name])
            ->toArray();
    }

    public function table(Table $table): Table
    {
        $config = json_decode(file_get_contents(__DIR__ . '/layouts_table.json'), true);
        return JsonTableBuilder::build($table, $config);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $moduleId = $this->getOwnerRecord()->id;

        $exists = ModuleLayout::where('module_id', $moduleId)->where('layout_type', $data['layout_type'] ?? '')->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.layout_type' => "A \"{$data['layout_type']}\" layout already exists for this module.",
            ]);
        }

        $data['module_id'] = $moduleId;
        return $data;
    }
}
