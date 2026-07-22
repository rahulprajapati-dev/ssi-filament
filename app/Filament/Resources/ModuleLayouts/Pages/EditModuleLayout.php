<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\Concerns\HasModuleFieldPool;
use App\Filament\Resources\ModuleLayouts\Hooks\ModuleLayoutHooks;
use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditModuleLayout extends EditRecord
{
    use HasModuleFieldPool;

    protected static string $resource = ModuleLayoutResource::class;

    public function getTitle(): string
    {
        return 'Edit Layout';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function resolveLayoutType(): ?string
    {
        return $this->record?->layout_type;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        static::ensureLayoutTypeIsUnique(
            moduleId:   (int) ($data['module_id'] ?? $this->record->module_id),
            layoutType: (string) $data['layout_type'],
            ignoreId:   (int) $this->record->id,
        );

        return app(ModuleLayoutHooks::class)->applyLayoutInheritance($data, $this->record);
    }
}
