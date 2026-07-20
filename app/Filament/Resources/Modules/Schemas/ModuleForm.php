<?php

namespace App\Filament\Resources\Modules\Schemas;

use App\Helpers\JsonStudioFormBuilder;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;

class ModuleForm
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

        $fullPath = base_path("app/Filament/Resources/Modules/Schemas/{$configPath}");

        $raw    = File::exists($fullPath) ? json_decode(file_get_contents($fullPath), true) : null;
        $config = is_array($raw) ? $raw : [];

        return JsonStudioFormBuilder::buildSchema($schema, $config);
    }
}
