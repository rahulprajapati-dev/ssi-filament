<?php

namespace App\Filament\Resources\Modules\RelationManagers;

use App\Filament\Resources\ModuleLayouts\Concerns\HasModuleFieldPool;
use App\Filament\Resources\ModuleLayouts\Hooks\ModuleLayoutHooks;
use App\Helpers\JsonStudioFormBuilder;
use App\Helpers\JsonTableBuilder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LayoutsRelationManager extends RelationManager
{
    use HasModuleFieldPool;

    protected static string $relationship = 'layouts';

    protected static ?string $title = 'Layouts';

    public function form(Schema $schema): Schema
    {
        $config = json_decode(file_get_contents(__DIR__ . '/layouts_form.json'), true);

        $moduleId = $this->getOwnerRecord()->id;

        $fields = static::loadFieldsForLayout($moduleId, null);

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

    public function table(Table $table): Table
    {
        $config = json_decode(file_get_contents(__DIR__ . '/layouts_table.json'), true);
        return JsonTableBuilder::build($table, $config);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $moduleId = $this->getOwnerRecord()->id;

        static::ensureLayoutTypeIsUnique(
            moduleId:   $moduleId,
            layoutType: (string) ($data['layout_type'] ?? ''),
        );

        $data['module_id'] = $moduleId;
        return app(ModuleLayoutHooks::class)->applyLayoutInheritance($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $moduleId = $this->getOwnerRecord()->id;
        $record   = $this->getMountedTableActionRecord();

        static::ensureLayoutTypeIsUnique(
            moduleId:   $moduleId,
            layoutType: (string) ($data['layout_type'] ?? ''),
            ignoreId:   $record ? (int) $record->id : null,
        );

        return app(ModuleLayoutHooks::class)->applyLayoutInheritance($data, $record);
    }
}
