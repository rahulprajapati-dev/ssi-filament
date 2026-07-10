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
     * For each enabled toggle: if the module already has that view type, its
     * layout_json is pulled into the record being saved; if it doesn't exist
     * yet, it's created now using the layout_json being saved.
     */
    public function applyLayoutInheritance(array $data, ?ModuleLayout $currentRecord = null): array
    {
        $moduleId = $data['module_id'] ?? null;

        if (blank($moduleId)) {
            return $data;
        }

        /**
         * Only run inheritance when creating Create View
         */
        if (($data['layout_type'] ?? null) !== 'create') {
            return $data;
        }

        $createLayoutJson = $data['layout_json'] ?? [];

        $inheritLayouts = [
            'inherit_edit_layout' => 'edit',
            'inherit_detail_layout' => 'detail',
        ];

        foreach ($inheritLayouts as $toggle => $targetType) {

            if (empty($data[$toggle])) {
                continue;
            }

            $existingLayout = ModuleLayout::where('module_id', $moduleId)
                ->where('layout_type', $targetType)->first();

            if ($existingLayout) {

                $existingLayout->update([
                    'layout_json' => $createLayoutJson,
                ]);

                Notification::make()
                    ->title(ucfirst($targetType).' View updated')
                    ->body('Layout copied from Create View.')->success()->send();

            } else {

                ModuleLayout::create([
                    'module_id'   => $moduleId,
                    'layout_type' => $targetType,
                    'layout_json' => $createLayoutJson,
                ]);

                Notification::make()
                    ->title(ucfirst($targetType).' View created')
                    ->body('Layout copied from Create View.')->success()->send();
            }
        }

        return $data;
    }
}