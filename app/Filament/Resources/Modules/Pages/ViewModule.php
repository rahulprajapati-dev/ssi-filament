<?php

namespace App\Filament\Resources\Modules\Pages;

use App\Filament\Resources\Modules\Hooks\ModuleHooks;
use App\Filament\Resources\Modules\ModuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewModule extends ViewRecord
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ModuleHooks::repairRebuildAction()->visible(fn () => $this->record->is_deploy),
        ];
    }
}
