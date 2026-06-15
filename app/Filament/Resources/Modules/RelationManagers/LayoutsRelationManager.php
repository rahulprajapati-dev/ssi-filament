<?php

namespace App\Filament\Resources\Modules\RelationManagers;

use App\Helpers\JsonStudioFormBuilder;
use App\Helpers\JsonTableBuilder;
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
        return JsonStudioFormBuilder::buildSchema($schema, $config);
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
