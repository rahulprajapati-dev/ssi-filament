<?php

namespace App\Filament\Resources\Studios\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;
use App\Helpers\JsonTableBuilder;

class StudiosTable
{
    public static function configure(Table $table): Table
    {
          $path = base_path('app/Filament/Resources/Studios/Tables/Studio_table.json'); // update if file location differs

        $config = [];
        if (File::exists($path)) {
            $config = json_decode(File::get($path), true) ?: [];
        }

        return JsonTableBuilder::build($table, $config);
    }
}
