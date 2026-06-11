<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Directly repairs a module's database table to match its current field definitions.
 *
 * Used by the Repair/Rebuild action so new or changed fields are reflected in the
 * database without generating or re-running migration files.
 *
 * What it does:
 *   1. Creates the table if it doesn't exist yet.
 *   2. Adds columns that are missing from the table.
 *   3. Attempts to modify columns whose length changed (string columns only).
 *      Type changes that could cause data-loss are intentionally skipped.
 */
final class SchemaRepairer
{
    private const STRING_TYPES = ['text', 'string', 'email', 'url', 'phone', 'password', 'select', 'dropdown', 'radio'];

    /**
     * @return bool  True when at least one schema change was made.
     */
    public static function repair(Module $module): bool
    {
        $table = Str::snake(Str::plural((string) $module->name));

        $fields = $module->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($fields->isEmpty()) {
            return false;
        }

        if (! Schema::hasTable($table)) {
            self::createTable($table, $fields);
            return true;
        }

        return self::reconcileTable($table, $fields);
    }

    // ─── Table creation ───────────────────────────────────────────────────────

    private static function createTable(string $table, $fields): void
    {
        Schema::create($table, function (Blueprint $blueprint) use ($fields) {
            $blueprint->id();

            foreach ($fields as $field) {
                self::addColumn($blueprint, $field);
            }

            $blueprint->string('created_by', 36)->nullable()->index();
            $blueprint->string('updated_by', 36)->nullable()->index();
            $blueprint->timestamps();
        });
    }

    // ─── Reconcile existing table ─────────────────────────────────────────────

    private static function reconcileTable(string $table, $fields): bool
    {
        $existingColumns = Schema::getColumnListing($table);
        $changed         = false;

        // Pass 1 – add missing columns
        $missing = $fields->filter(fn (ModuleField $f) => ! in_array($f->field_name, $existingColumns, true));

        if ($missing->isNotEmpty()) {
            Schema::table($table, function (Blueprint $blueprint) use ($missing) {
                foreach ($missing as $field) {
                    self::addColumn($blueprint, $field);
                }
            });
            $changed = true;
        }

        // Pass 2 – attempt to modify string columns whose length changed
        $present = $fields->filter(fn (ModuleField $f) => in_array($f->field_name, $existingColumns, true));

        foreach ($present as $field) {
            if (! self::isStringType($field->type)) {
                continue;
            }

            $desiredLength = ($field->length > 0) ? (int) $field->length : 255;

            try {
                $currentLength = self::getColumnLength($table, $field->field_name);

                if ($currentLength !== null && $currentLength !== $desiredLength) {
                    Schema::table($table, function (Blueprint $blueprint) use ($field, $desiredLength) {
                        $col = $blueprint->string($field->field_name, $desiredLength);
                        if (! $field->required) {
                            $col->nullable();
                        }
                        $col->change();
                    });
                    $changed = true;
                }
            } catch (\Throwable) {
                // Silently skip unsupported modifications
            }
        }

        return $changed;
    }

    // ─── Column definition ────────────────────────────────────────────────────

    private static function addColumn(Blueprint $blueprint, ModuleField $field): void
    {
        $name     = $field->field_name;
        $nullable = ! $field->required;
        $unique   = $field->unique_field;
        $length   = ($field->length > 0) ? (int) $field->length : 255;

        $col = match ($field->type) {
            'textarea', 'longtext', 'richtext'  => $blueprint->text($name),
            'integer', 'number', 'int'          => $blueprint->integer($name),
            'biginteger', 'bigint'              => $blueprint->bigInteger($name),
            'decimal', 'float', 'money'         => $blueprint->decimal($name, 15, 4),
            'boolean', 'toggle', 'checkbox'     => $blueprint->boolean($name)->default(false),
            'date'                              => $blueprint->date($name),
            'datetime', 'timestamp'             => $blueprint->dateTime($name),
            'time'                              => $blueprint->time($name),
            'json', 'array', 'repeater'         => $blueprint->json($name),
            default                             => $blueprint->string($name, $length),
        };

        $isBool = in_array($field->type, ['boolean', 'toggle', 'checkbox'], true);
        $isText = in_array($field->type, ['textarea', 'longtext', 'richtext', 'json', 'array', 'repeater'], true);

        if ($nullable && ! $isBool) {
            $col->nullable();
        }

        if ($unique && ! $isBool && ! $isText) {
            $col->unique();
        }

        if ($field->default_value !== null && $field->default_value !== '' && ! $isBool) {
            $col->default($field->default_value);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function isStringType(string $type): bool
    {
        return in_array(strtolower($type), self::STRING_TYPES, true);
    }

    private static function getColumnLength(string $table, string $column): ?int
    {
        try {
            $result = DB::selectOne(
                "SELECT CHARACTER_MAXIMUM_LENGTH as len
                 FROM information_schema.COLUMNS
                 WHERE TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1",
                [$table, $column]
            );

            return $result ? (int) $result->len : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
