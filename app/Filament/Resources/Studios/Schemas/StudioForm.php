<?php

namespace App\Filament\Resources\Studios\Schemas;
use App\Helpers\JsonFormBuilder;
use Filament\Schemas\Schema;

class StudioForm
{
    public static function configure(Schema $schema): Schema
    {
      
        $operation = $schema->getOperation(); // 'create' | 'edit' | 'view'
        
        $configPath = match ($operation) {
            'create' => 'Studio_form_tabs.json',
            'edit'   => 'Studio_form_tabs.json',
            default  => 'studio_form_tabs_view.json',
        };

        $config = json_decode(
            file_get_contents(
                base_path("app/Filament/Resources/Studios/Schemas/{$configPath}")
            ),
            true
        );
        return JsonFormBuilder::buildSchema($schema, $config);
    }
}
