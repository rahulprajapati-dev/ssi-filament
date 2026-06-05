<?php

namespace App\Filament\Resources\ModuleFields\Schemas;

use App\Helpers\JsonStudioFormBuilder;
use Filament\Schemas\Schema;

class ModuleFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        $operation = $schema->getOperation(); // 'create' | 'edit' | 'view'

        $configPath = match ($operation) {
            'create' => 'createView.json',
            'edit' => 'editView.json',
            'view' => 'detailView.json',
            default => 'default.json',
        };

        $config = json_decode(
            file_get_contents(
                base_path("app/Filament/Resources/ModuleFields/Schemas/{$configPath}")
            ),
            true
        );

        return JsonStudioFormBuilder::buildSchema($schema, $config);
    }
}
