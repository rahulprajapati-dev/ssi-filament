<?php

namespace App\Helpers;
use Illuminate\Support\Str;
use App\Models\Module;
use App\Models\ModuleField;

class CommonHelper
{
    /**
     * Create a new class instance.
     */
    public static function populatemodulelabes(?string $label = null): array
    {
        return self::getformatelabel($label);
    }

    public static function getformatelabel(?string $label = null): array
    {
        return [
            'singular_label' => Str::studly($label) ?: null,
            'plural_label' => Str::studly(Str::plural($label)) ?: null,
        ];

    }
    
    public static function populatefieldlabel(?string $label = null): array
    {
        return [
            'label' => Str::headline($label),
        ];
    }

    public static function getModuleFieldsOptions($moduleId = null): array
    {
        if (! $moduleId) {
            return [];
        }
        return ModuleField::where('module_id', $moduleId)->pluck('label', 'field_name')->toArray();
    }

    public static function getModulesOptions(): array
    {
        return Module::orderBy('plural_label')->pluck('plural_label', 'name')->toArray();
    }

    public static function getModulesOptionsExcluding(string $selfName = ''): array
    {
        $query = Module::orderBy('plural_label');

        if ($selfName !== '') {
            $query->where('name', '!=', $selfName);
        }

        return $query->pluck('plural_label', 'name')->toArray();
    }

    public static function getForeignKeyOptions(?string $relatedModule = '', ?string $type = '', ?string $selfName = ''): array
    {
        // belongsTo / belongsToMany → FK lives on THIS model's table
        $targetName = in_array($type, ['belongsTo', 'belongsToMany'])
            ? $selfName
            : $relatedModule;

        if (! $targetName) {
            return [];
        }

        $module = Module::where('name', $targetName)->first();

        if (! $module) {
            return [];
        }

        return ModuleField::where('module_id', $module->id)
            ->get(['field_name', 'label'])
            ->mapWithKeys(fn ($f) => [$f->field_name => $f->field_name . ' — ' . $f->label])
            ->toArray();
    }
}
