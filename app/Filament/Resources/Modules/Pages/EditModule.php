<?php

namespace App\Filament\Resources\Modules\Pages;

use App\Filament\Resources\Modules\ModuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Modules\Hooks\ModuleHooks;

class EditModule extends EditRecord
{
    protected static string $resource = ModuleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['key']  = strtolower(trim($data['key'] ?? ''));
        $data['name'] = strtolower(trim($data['name'] ?? ''));
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->visible(fn () => !$this->record?->is_deploy),
            ModuleHooks::repairRebuildAction()->visible(fn () => $this->record?->is_deploy),
        ];
    }
}
