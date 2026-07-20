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

    /**
     * Handles the "inherit_edit_layout" / "inherit_detail_layout" toggles.
     *
     * PUSH: copies the current Create View's layout_json into the Edit and/or
     * Detail layout records for the same module. If the target layout doesn't
     * exist yet it is created automatically.
     */
    public function applyLayoutInheritance(array $data, ?ModuleLayout $currentRecord = null): array
    {
        // module_id may not be a visible form field on the edit page — fall back to the record.
        $moduleId = $data['module_id'] ?? $currentRecord?->module_id;

        if (blank($moduleId)) {
            return $data;
        }

        if (($data['layout_type'] ?? null) !== 'create') {
            return $data;
        }

        $createLayoutJson = $data['layout_json'] ?? [];

        $targets = [
            'inherit_edit_layout'   => 'edit',
            'inherit_detail_layout' => 'detail',
        ];

        foreach ($targets as $toggle => $targetType) {
            if (empty($data[$toggle])) {
                continue;
            }

            $existing = ModuleLayout::where('module_id', $moduleId)
                ->where('layout_type', $targetType)
                ->first();

            if ($existing) {
                $existing->update(['layout_json' => $createLayoutJson]);

                Notification::make()
                    ->title(ucfirst($targetType).' View updated')
                    ->body('Layout copied from Create View.')
                    ->success()
                    ->send();
            } else {
                ModuleLayout::create([
                    'module_id'   => $moduleId,
                    'layout_type' => $targetType,
                    'layout_json' => $createLayoutJson,
                ]);

                Notification::make()
                    ->title(ucfirst($targetType).' View created')
                    ->body('Layout copied from Create View.')
                    ->success()
                    ->send();
            }
        }

        return $data;
    }
}