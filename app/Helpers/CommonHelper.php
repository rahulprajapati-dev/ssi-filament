<?php

namespace App\Helpers;
use Illuminate\Support\Str;
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
    
    public static function populatefieldlabel(string $label = null): array
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
}
