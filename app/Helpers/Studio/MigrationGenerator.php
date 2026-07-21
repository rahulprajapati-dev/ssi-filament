<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Helpers\Studio\FieldTypeMap;

/**
 * Generates a database migration file from a module's field definitions.
 *
 * Returns:
 *   true  — migration file written
 *   false — a migration for this table already exists (skipped)
 *
 * Column mapping (ModuleField::type → Blueprint method):
 *   text / string / email / url / phone / password / select / radio → string(length)
 *   textarea / longtext / richtext                                  → text
 *   integer / number / int                                          → integer
 *   biginteger / bigint                                             → bigInteger
 *   decimal / float / money                                         → decimal(15,4)
 *   boolean / toggle / checkbox                                     → boolean
 *   date                                                            → date
 *   datetime / timestamp                                            → dateTime
 *   time                                                            → time
 *   json / array / repeater                                         → json
 */
final class MigrationGenerator
{
    /** Indent for columns inside the Schema::create callback (3 levels × 4 spaces). */
    private const INDENT = '            ';

    public static function generate(Module $module): bool
    {
        $table = strtolower($module->computed_table);


        $existing = glob(database_path("migrations/*_create_{$table}_table.php"));

        // [M7] glob() returns false on error; treat that as "no existing files".
        if (is_array($existing) && ! empty($existing)) {
            // If the table already exists the migration has been applied — don't touch it.
            if (Schema::hasTable($table)) {
                return false;
            }
            // Table doesn't exist yet: the old migration file is stale (e.g. a failed
            // previous deploy). Safe to delete and regenerate with current field definitions.
            foreach ($existing as $file) {
                File::delete($file);
            }
        }

        $createColumns = self::buildColumnBlock($module);
        $updateColumns = self::buildUpdateColumnBlock($module);
        $filename = date('Y_m_d_His') . "_create_{$table}_table.php";

        $content = StubRenderer::render('Migration.stub', [
            'TABLE'   => $table,
            'COLUMNS'         => $createColumns,
            'COLUMNS_UPDATE'  => $updateColumns,
        ]);

        File::put(database_path("migrations/{$filename}"), $content);

        return true;
    }

    // --------------------------------------------------------------------------
    // Column block builder
    // --------------------------------------------------------------------------

    private static function buildUpdateColumnBlock(Module $module): string
    {
        $fields = $module->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($fields->isEmpty()) {
            return '';
        }

        return $fields->map(function (ModuleField $field) {

            // System columns are handled explicitly by the stub — skip to avoid duplicates.
            if (in_array($field->field_name, FieldTypeMap::SYSTEM_FIELD_NAMES, true)) {
                return '';
            }

            // Address parent is virtual — skip it.
            if ($field->type === 'address') {
                return '';
            }

            $name = $field->field_name;
            $pad  = self::INDENT;

            return "{$pad}if (!in_array('{$name}', \$columns)) {"
                . "\n{$pad}    " . self::columnLineRaw($field)
                . "\n{$pad}}";

        })->filter()->implode("\n") . "\n";
    }

    private static function columnLineRaw(ModuleField $field): string
    {
        $name   = $field->field_name;
        $null   = $field->required ? '' : '->nullable()';
        $unique = $field->unique_field ? '->unique()' : '';

        // System columns are handled explicitly by the stub — skip to avoid duplicates.
        if (in_array($name, FieldTypeMap::SYSTEM_FIELD_NAMES, true)) {
            return '';
        }

        // Address parent is virtual — no own column.
        if ($field->type === 'address') {
            return '';
        }

        // Multi-value fields store arrays → json column
        if (! empty($field->is_multiple) && in_array($field->type, ['select', 'dropdown', 'enum', 'file', 'image', 'fileupload'], true)) {
            return "\$table->json('{$name}'){$null};";
        }

        return match ($field->type) {
            'textarea', 'longtext', 'richtext'  => "\$table->text('{$name}'){$null};",
            'integer', 'number', 'int'          => "\$table->integer('{$name}'){$null}{$unique};",
            'biginteger', 'bigint'              => "\$table->bigInteger('{$name}'){$null}{$unique};",
            'decimal', 'float', 'money', 'currency' => "\$table->decimal('{$name}', 15, 4){$null}{$unique};",
            'boolean', 'toggle', 'checkbox'     => "\$table->boolean('{$name}')->default(false);",
            'date'                              => "\$table->date('{$name}'){$null};",
            'datetime', 'timestamp'             => "\$table->dateTime('{$name}'){$null};",
            'time'                              => "\$table->time('{$name}'){$null};",
            'json', 'array', 'repeater',
            'checkbox_list', 'checkboxlist',
            'tags'                              => "\$table->json('{$name}'){$null};",
            default                             => "\$table->string('{$name}', " . FieldTypeMap::resolveLength($field) . "){$null}{$unique};",
        };
    }

    public static function remove(Module $module): bool
    {
        $table = strtolower($module->computed_table);

        $files = glob(database_path("migrations/*_create_{$table}_table.php"));

        // [M7] glob() returns false on error; treat that as "nothing to remove".
        if (! is_array($files) || empty($files)) {
            return false;
        }

        foreach ($files as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        return true;
    }

    private static function buildColumnBlock(Module $module): string
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ModuleField> $fields */
        $fields = $module->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($fields->isEmpty()) {
            return '';
        }

        $lines = $fields
            ->map(fn (ModuleField $field): string => self::columnLine($field))
            ->filter()
            ->values();

        return $lines->implode("\n") . "\n";
    }

    private static function columnLine(ModuleField $field): string
    {
        $name   = $field->field_name;
        $null   = $field->required   ? ''         : '->nullable()';
        $unique = $field->unique_field ? '->unique()' : '';
        $pad    = self::INDENT;

        // System columns are added explicitly by the stub — skip to avoid duplicates.
        if (in_array($name, FieldTypeMap::SYSTEM_FIELD_NAMES, true)) {
            return '';
        }

        // Address parent is virtual — its sub-fields hold the real DB columns.
        if ($field->type === 'address') {
            return '';
        }

        // Multi-value fields store arrays → json column
        if (! empty($field->is_multiple) && in_array($field->type, ['select', 'dropdown', 'enum', 'file', 'image', 'fileupload'], true)) {
            return "{$pad}\$table->json('{$name}'){$null};";
        }

        return match ($field->type) {
            'textarea', 'longtext', 'richtext'
                => "{$pad}\$table->text('{$name}'){$null}" . self::defaultStr($field) . ';',

            'integer', 'number', 'int'
                => "{$pad}\$table->integer('{$name}'){$null}{$unique}" . self::defaultNum($field) . ';',

            'biginteger', 'bigint'
                => "{$pad}\$table->bigInteger('{$name}'){$null}{$unique}" . self::defaultNum($field) . ';',

            'decimal', 'float', 'money', 'currency'
                => "{$pad}\$table->decimal('{$name}', 15, 4){$null}{$unique}" . self::defaultNum($field) . ';',

            'boolean', 'toggle', 'checkbox'
                => "{$pad}\$table->boolean('{$name}')->default(false);",

            'date'
                => "{$pad}\$table->date('{$name}'){$null}" . self::defaultStr($field) . ';',

            'datetime', 'timestamp'
                => "{$pad}\$table->dateTime('{$name}'){$null}" . self::defaultStr($field) . ';',

            'time'
                => "{$pad}\$table->time('{$name}'){$null}" . self::defaultStr($field) . ';',

            'json', 'array', 'repeater', 'checkbox_list', 'checkboxlist', 'tags'
                => "{$pad}\$table->json('{$name}'){$null};",

            default // string, text, email, url, phone, password, select, radio, etc.
                => "{$pad}\$table->string('{$name}', " . FieldTypeMap::resolveLength($field) . "){$null}{$unique}" . self::defaultStr($field) . ';',
        };
    }



    private static function hasDefault(ModuleField $field): bool
    {
        return $field->default_value !== null && $field->default_value !== '';
    }

    private static function defaultStr(ModuleField $field): string
    {
        if (! self::hasDefault($field)) {
            return '';
        }

        return "->default('" . addslashes((string) $field->default_value) . "')";
    }

    private static function defaultNum(ModuleField $field): string
    {
        // [H10] Validate that default_value is genuinely numeric before injecting
        // it as a PHP literal to prevent arbitrary code injection.
        $val = $field->default_value;
        if (! $val || ! is_numeric((string) $val)) {
            return '';
        }
        $typed = (strpos((string) $val, '.') !== false) ? (float) $val : (int) $val;
        return '->default(' . $typed . ')';
    }
}
