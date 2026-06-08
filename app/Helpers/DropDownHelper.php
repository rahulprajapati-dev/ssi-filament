<?php

namespace App\Helpers;

class DropDownHelper
{
    /**
     * Get General Dropdown Options (Status, Source, etc.)
     */
    public static function getDropdown($key)
    {
        $filePath = base_path('SSI/Dropdowns/list.json');

        if (!file_exists($filePath)) {
            return [];
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data[$key])) {
            return [];
        }

        return is_array($data[$key]) ? $data[$key] : [];
    }


}
