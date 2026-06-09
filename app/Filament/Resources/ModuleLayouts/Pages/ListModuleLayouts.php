<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModuleLayouts extends ListRecords
{
    protected static string $resource = ModuleLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Layout')->icon('heroicon-o-plus'),
        ];
    }
}
