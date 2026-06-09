<?php

namespace App\Helpers;

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
            'singular_label' => $label ?: null,
            'plural_label' => $label ?: null,
        ];

    }
     public static function populatefieldlabel(string $label = null): array
    {
        return [
            'label'=>$label
        ];
    }

}
