<?php

namespace App\Filament\Resources\ModuleLayouts\Hooks;

use App\Models\ModuleLayout;
use Filament\Notifications\Notification;


class ModuleLayoutHooks
{
    /**
     * Fires on change of either the Module or the View Type select (both are
     * wired to this hook), so it always reads both values fresh via $get()
     * rather than trusting $state to be a particular field.
     */
    public function validateUniqueModuleLayout($get, $record)
    {
        $moduleId = $get('module_id');
        $layoutType = $get('layout_type');

        if (blank($moduleId) || blank($layoutType)) {
            return;
        }

        $exists = ModuleLayout::query()
            ->when($record, fn ($q) => $q->whereKeyNot($record->id))
            ->where('module_id', $moduleId)
            ->where('layout_type', $layoutType)
            ->exists();

        if ($exists) {
            return [
                'status' => true,
                'error' => "A \"{$layoutType}\" layout already exists for this module. Each layout type can only be defined once per module.",
            ];
        }
    }
}