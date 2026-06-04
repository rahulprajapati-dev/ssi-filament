<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class SchemaDiffer
{
    public static function diff(string $table, string $connection): array
    {
        $defaultPath = base_path("schemas/default/{$table}.json");

        if (!File::exists($defaultPath)) {
            throw new \Exception("Default schema for '{$table}' not found. Please run `php artisan schema:export {$table}` first.");
        }

        $defaultSchema = json_decode(File::get($defaultPath), true)['fields'] ?? [];
        $currentSchema = DbSchemaExporter::export($table, $connection)['fields'] ?? [];

        $diffFields = array_diff_key($currentSchema, $defaultSchema);

        if (empty($diffFields)) {
            return [];
        }

        return ['fields' => $diffFields];
    }
}