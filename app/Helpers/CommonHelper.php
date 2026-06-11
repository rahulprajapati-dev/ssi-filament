<?php

namespace App\Helpers;
use Illuminate\Support\Str;

class CommonHelper
{
    /**
     * Create a new class instance.
     */
    public static function populatemodulelabes(string $label = null): array
    {
        return self::getformatelabel($label);
    }
    public static function getformatelabel(string $label = null): array
    {
        return [
            'singular_label' => Str::studly($label) ?: null,
            'plural_label' => Str::studly(Str::plural($label)) ?: null,
        ];

    }
    
    public static function populatefieldlabel(string $label = null): array
    {
        return [
            'label'=> Str::studly($label),
        ];
    }

}
