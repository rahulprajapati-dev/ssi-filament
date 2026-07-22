<?php

namespace App\Filament\Resources\ModuleLayouts\Pages;

use App\Filament\Resources\ModuleLayouts\Concerns\HasModuleFieldPool;
use App\Filament\Resources\ModuleLayouts\Hooks\ModuleLayoutHooks;
use App\Filament\Resources\ModuleLayouts\ModuleLayoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModuleLayout extends CreateRecord
{
    use HasModuleFieldPool;

    protected static string $resource = ModuleLayoutResource::class;

    public function getTitle(): string
    {
        return 'Create Layout';
    }

    protected function resolveLayoutType(): ?string
    {
        return $this->data['layout_type'] ?? null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        static::ensureLayoutTypeIsUnique(
            moduleId:   (int) $data['module_id'],
            layoutType: (string) $data['layout_type'],
        );

        return app(ModuleLayoutHooks::class)->applyLayoutInheritance($data);
    }
}
