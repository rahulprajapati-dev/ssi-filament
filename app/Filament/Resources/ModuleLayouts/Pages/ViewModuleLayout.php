<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewModuleLayout extends ViewRecord
{
    protected static string $resource = ModuleLayoutResource::class;
     public function getTitle(): string 
    {
        return 'View Layout';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
