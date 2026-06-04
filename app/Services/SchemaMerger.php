<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class SchemaMerger
{
    public static function merge(string $table, string $brand): array
    {
        $defaultPath = base_path("schemas/default/{$table}.json");
        $brandPath = base_path("schemas/{$brand}/{$table}.json");

        if (!File::exists($defaultPath)) {
            throw new \Exception("Default schema for '{$table}' not found.");
        }

        $defaultSchema = json_decode(File::get($defaultPath), true);
        $brandSchema = [];
        if (File::exists($brandPath)) {
            $brandSchema = json_decode(File::get($brandPath), true);
        }

        $mergedFields = array_merge($defaultSchema['fields'] ?? [], $brandSchema['fields'] ?? []);

        return ['fields' => $mergedFields];
    }
}