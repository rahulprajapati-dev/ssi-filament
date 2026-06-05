<?php

namespace App\Filament\Resources\Modules\Tables;

use App\Helpers\JsonTableBuilder;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;

class ModulesTable
{
    public static function configure(Table $table): Table
    {
        $path = base_path('app/Filament/Resources/Modules/Tables/listView.json');

        $config = [];
        if (File::exists($path)) {
            $config = json_decode(File::get($path), true) ?: [];
        }

        return JsonTableBuilder::build($table, $config);
    }
}
