<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Helpers\Studio\FieldTypeMap;

/**
 * SchemaSyncService — direct database schema management without migration files.
 *
 * Idempotent: safe to call multiple times on the same module.
 * - Creates the table if it does not exist.
 * - Adds columns that are missing from the existing table.
 * - Attempts to adjust string column lengths when they change.
 * - Never drops columns (prevents accidental data loss).
 *
 * Usage:
 *   SchemaSyncService::sync($module);
 */
final class SchemaSyncService
{
    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Ensure the module's database table exists and contains all defined fields.
     */
    public static function sync(Module $module): void
    {
        if (! Schema::hasTable(self::tableName($module))) {
            self::createTable($module);
        } else {
            self::syncColumns($module);
        }
    }

    /**
     * Create the module's table from scratch with all current field definitions.
     */
    public static function createTable(Module $module): void
    {
        $table  = self::tableName($module);
        $fields = self::orderedFields($module);

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

    /**
     * Add columns that are present in ModuleField but absent from the table.
     * Also adjusts string column lengths when they change (non-destructive).
     */
    public static function syncColumns(Module $module): void
    {
        $table           = self::tableName($module);
        $existingColumns = Schema::getColumnListing($table);
        $fields          = self::orderedFields($module);

        // ── Pass 1: add missing columns ───────────────────────────────────────
        $missing = $fields->filter(
            fn (ModuleField $f) => ! in_array($f->field_name, $existingColumns, true)
        );

        if ($missing->isNotEmpty()) {
            Schema::table($table, function (Blueprint $blueprint) use ($missing) {
                foreach ($missing as $field) {
                    self::addColumn($blueprint, $field);
                }
            });
        }

        // ── Pass 2: adjust string column lengths ──────────────────────────────
        $present = $fields->filter(
            fn (ModuleField $f) => in_array($f->field_name, $existingColumns, true)
                && self::isStringType($f->type)
        );

        foreach ($present as $field) {
            $desired = FieldTypeMap::resolveLength($field);
            $current = self::getColumnLength($table, $field->field_name);

            if ($current !== null && $current !== $desired) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($field, $desired) {
                        $col = $blueprint->string($field->field_name, $desired);
                        if (! $field->required) {
                            $col->nullable();
                        }
                        $col->change();
                    });
                } catch (\Throwable) {
                    // Silently skip — driver may not support ALTER on this column.
                }
            }
        }
    }

    /**
     * Append a single column definition to a Blueprint.
     *
     * Handles: nullable, default value, unique constraint.
     * Called from both createTable() and syncColumns().
     */
    public static function addColumn(Blueprint $blueprint, ModuleField $field): void
    {
        // Address parent is virtual — its sub-fields hold the real DB columns.
        if (FieldTypeMap::isAddressType($field->type)) {
            return;
        }

        $name   = $field->field_name;
        $isBool = FieldTypeMap::isBooleanType($field->type);

        // Multi-value fields (multiple select/file/image) store JSON arrays
        if (! empty($field->is_multiple) && in_array(strtolower($field->type), ['select', 'dropdown', 'enum', 'file', 'image', 'fileupload'], true)) {
            $col = $blueprint->json($name);
            if (! $field->required) {
                $col->nullable();
            }
            return;
        }

        $col = match (strtolower($field->type)) {
            'textarea', 'longtext', 'richtext'  => $blueprint->text($name),
            'integer', 'number', 'int'          => $blueprint->integer($name),
            'biginteger', 'bigint'              => $blueprint->bigInteger($name),
            'decimal', 'float', 'money'         => $blueprint->decimal($name, 15, 4),
            'boolean', 'toggle', 'checkbox'     => $blueprint->boolean($name)->default(false),
            'date'                              => $blueprint->date($name),
            'datetime', 'timestamp'             => $blueprint->dateTime($name),
            'time'                              => $blueprint->time($name),
            'json', 'array', 'repeater'         => $blueprint->json($name),
            default                             => $blueprint->string($name, FieldTypeMap::resolveLength($field)),
        };

        // nullable — boolean columns always have a default so nullable is unnecessary
        if (! $field->required && ! $isBool) {
            $col->nullable();
        }

        if ($field->unique_field && FieldTypeMap::supportsUnique($field->type)) {
            $col->unique();
        }

        if (FieldTypeMap::supportsDefault($field->type) && $field->default_value !== null && $field->default_value !== '') {
            $col->default($field->default_value);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function tableName(Module $module): string
    {
        return Str::snake(Str::plural((string) $module->fullname));
    }

    /** @return Collection<int, ModuleField> */
    private static function orderedFields(Module $module): Collection
    {
        return $module->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private static function isStringType(string $type): bool
    {
        return FieldTypeMap::isStringType($type);
    }

    /**
     * Query information_schema for the current CHARACTER_MAXIMUM_LENGTH of a column.
     * Returns null when the information is unavailable (e.g. SQLite).
     */
    private static function getColumnLength(string $table, string $column): ?int
    {
        try {
            $row = DB::selectOne(
                "SELECT CHARACTER_MAXIMUM_LENGTH AS len
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND COLUMN_NAME  = ?
                 LIMIT 1",
                [$table, $column]
            );

            return $row ? (int) $row->len : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
