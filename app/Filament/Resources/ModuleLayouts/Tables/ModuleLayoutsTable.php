<?php

namespace App\Filament\Resources\ModuleLayouts\Tables;

use App\Helpers\JsonTableBuilder;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;

class ModuleLayoutsTable
{
    public static function configure(Table $table): Table
    {
        $path = base_path('app/Filament/Resources/ModuleLayouts/Tables/listView.json');

        $config = [];
        if (File::exists($path)) {
            $config = json_decode(File::get($path), true) ?: [];
        }

        return JsonTableBuilder::build($table, $config);
    }
}
