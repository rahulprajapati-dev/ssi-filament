<?php

namespace App\Filament\Resources\Modules\Hooks;

use App\Helpers\Studio\StudioManager;
use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ModuleHooks
{
    public function toggleModule(Module $record, array $_data = []): array
    {
        $record->update(['is_enable' => ! $record->is_enable]);
        $record->refresh();
        if ($record->is_enable) { 
            $this->repairRebuild($record);
        }

        $status = $record->is_enable ? 'Enabled' : 'Disabled';

        Notification::make()
            ->success()
            ->title("Module {$status} Successfully")
            ->send();

        return ['success' => true];
    }

    public function deployModule(Module $record, array $_data = []): array
    {
        $result = $record->is_deploy
            ? StudioManager::rebuild($record)
            : StudioManager::deploy($record);

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
            'key'                => $record->key,
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

      public function uninstallwarning($state, $set, $get, $livewire, $record, $component)
      {
         $iscustom = $get('is_custom') ?? false;
         if ($iscustom) {
             $set('custom_dummy', 'This will permanently delete all customizations for this resource. Continue?');
         } else {
             $set('custom_dummy', null);
         }
         return ['status' => false];
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
    public function ValidateModuleName($attribute, $value, $fail, $record, $get)
    {
        $key = trim($get('key') ?? '');
        $name = trim($value ?? '');
        $pluralName = trim($get('plural_label'));
        $singularName = trim($get('singular_label'));

        $values = array_filter(array_unique(array_map('strtolower', [$name, $singularName, $pluralName])));

        $fullnameExists = Module::query()
            ->when($record, fn($q) => $q->where('id', '!=', $record->getKey()))
            ->where(function ($q) use ($key) {
                $key === '' ? $q->whereNull('key')->orWhere('key', '')
                    : $q->whereRaw('LOWER(`key`) = ?', [strtolower($key)]);
            })
            ->where(function ($q) use ($values) {
                $q->whereIn(DB::raw('LOWER(`name`)'), $values)
                ->orWhereIn(DB::raw('LOWER(`singular_label`)'), $values)
                ->orWhereIn(DB::raw('LOWER(`plural_label`)'), $values);
            })
            ->exists();

        if ($fullnameExists) {
            $fail($key !== ''
                ? "A module with Key \"{$key}\" and Name \"{$name}\" (or its singular/plural form) already exists."
                : "A module with Name \"{$name}\" (or its singular/plural form) already exists."
            );
        }

    }
    public function revalidateNameOnKeyChange($state, $get, $set, $component, $livewire, $statePath)
    {
        $livewire->validateOnly($statePath);
        $basePath = Str::contains($statePath, '.') ? Str::beforeLast($statePath, '.') : null;
        $namePath = $basePath ? "{$basePath}.name" : 'name';
        $livewire->validateOnly($namePath);
    }
}
