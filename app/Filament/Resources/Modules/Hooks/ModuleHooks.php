<?php

namespace App\Filament\Resources\Modules\Hooks;

use App\Helpers\Studio\StudioManager;
use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class ModuleHooks
{
    public function toggleModule(Module $record, array $_data = []): array
    {
        $record->update(['is_enable' => ! $record->is_enable]);
        $record->refresh();

        $status = $record->is_enable ? 'Enabled' : 'Disabled';

        Notification::make()
            ->success()
            ->title("Module {$status} Successfully")
            ->send();

        return ['success' => true];
    }

    public function deployModule(Module $record, array $_data = []): array
    {
        $result = StudioManager::deploy($record);

        if ($result->success) {
            Notification::make()->success()->title($result->message)->send();
        } else {
            Notification::make()->danger()->title('Deployment Failed')->body($result->message)->send();
        }

        return ['success' => $result->success];
    }

    public static function repairRebuildAction(): Action
    {
        return Action::make('repair_rebuild')
            ->label('Repair & Rebuild')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Repair & Rebuild Module')
            ->modalDescription('This will repair and rebuild the module by regenerating missing files and ensuring the database schema and layouts are up to date. Do you want to continue?')
            ->modalSubmitActionLabel('Yes, Repair & Rebuild')
            ->action(function (Module $record) {
                $result = StudioManager::rebuild($record);
                if ($result->success) {
                    Notification::make()->success()->title($result->message)->send();
                } else {
                    Notification::make()->danger()->title('Rebuild Failed')->body($result->message)->send();
                }
            });
    }

    public function repairRebuild(Module $record, array $_data = []): array
    {
        $result = StudioManager::rebuild($record);

        if ($result->success) {
            Notification::make()->success()->title($result->message)->send();
        } else {
            Notification::make()->danger()->title('Rebuild Failed')->body($result->message)->send();
        }

        return ['success' => $result->success];
    }

    public function cloneModule(Module $record, array $data = []): array
    {
        $newName = $data['name'] ?? ($record->name . '_copy');

        if (Module::where('name', $newName)->exists()) {
            Notification::make()->danger()->title('Clone Failed')->body("A module named '{$newName}' already exists.")->send();
            return ['success' => false];
        }

        $clone = Module::create([
            'name'               => $newName,
            'singular_label'     => ($data['singular_label'] ?? $record->singular_label) . ' (Copy)',
            'plural_label'       => ($data['plural_label'] ?? $record->plural_label) . ' (Copy)',
            'icon'               => $record->icon,
            'description'        => $record->description,
            'relationships_json' => $record->relationships_json,
            'use_uuid'           => $record->use_uuid,
            'is_deploy'          => false,
            'is_enable'          => false,
        ]);

        foreach ($record->fields as $field) {
            ModuleField::create([
                ...$field->only([
                    'field_name', 'label', 'type', 'length', 'required',
                    'searchable', 'sortable', 'unique_field', 'default_value',
                    'options', 'sort_order', 'visibility_mode', 'condition_logic',
                    'always_save_value', 'visibility_conditions',
                ]),
                'module_id' => $clone->id,
            ]);
        }

        foreach ($record->layouts as $layout) {
            ModuleLayout::create([
                'module_id'   => $clone->id,
                'layout_type' => $layout->layout_type,
                'layout_json' => $layout->layout_json,
            ]);
        }

        Notification::make()->success()->title('Module Cloned')->body("'{$clone->plural_label}' created successfully.")->send();

        return ['success' => true];
    }

    public function uninstall(Module $record, array $_data = []): array
    {
        $result = StudioManager::uninstall($record, $_data);

        if ($result->success) {
            Notification::make()->success()->title($result->message)->send();
        } else {
            Notification::make()->danger()->title('Uninstall Failed')->body($result->message)->send();
        }

        return ['success' => $result->success];
    }
}
