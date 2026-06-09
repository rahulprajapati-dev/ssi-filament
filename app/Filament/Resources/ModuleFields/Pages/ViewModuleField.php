<?php

namespace App\Filament\Resources\ModuleFields\Pages;

use App\Filament\Resources\ModuleFields\ModuleFieldResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewModuleField extends ViewRecord
{
    protected static string $resource = ModuleFieldResource::class;
    public function getTitle(): string 
    {
        return 'View Field';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
