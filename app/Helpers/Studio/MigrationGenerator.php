<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        $table = Str::snake(Str::plural((string) $module->name));

        // Idempotency: if any migration for this table already exists, skip.
        $existing = glob(database_path("migrations/*_create_{$table}_table.php"));
        if (! empty($existing)) {
            return false;
        }

        $columns  = self::buildColumnBlock($module);
        $filename = date('Y_m_d_His') . "_create_{$table}_table.php";

        $content = StubRenderer::render('Migration.stub', [
            'TABLE'   => $table,
            'COLUMNS' => $columns,
        ]);

        File::put(database_path("migrations/{$filename}"), $content);

        return true;
    }

    // --------------------------------------------------------------------------
    // Column block builder
    // --------------------------------------------------------------------------

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

        return match ($field->type) {
            'textarea', 'longtext', 'richtext'
                => "{$pad}\$table->text('{$name}'){$null}" . self::defaultStr($field) . ';',

            'integer', 'number', 'int'
                => "{$pad}\$table->integer('{$name}'){$null}{$unique}" . self::defaultNum($field) . ';',

            'biginteger', 'bigint'
                => "{$pad}\$table->bigInteger('{$name}'){$null}{$unique}" . self::defaultNum($field) . ';',

            'decimal', 'float', 'money'
                => "{$pad}\$table->decimal('{$name}', 15, 4){$null}{$unique}" . self::defaultNum($field) . ';',

            'boolean', 'toggle', 'checkbox'
                => "{$pad}\$table->boolean('{$name}')->default(false);",

            'date'
                => "{$pad}\$table->date('{$name}'){$null}" . self::defaultStr($field) . ';',

            'datetime', 'timestamp'
                => "{$pad}\$table->dateTime('{$name}'){$null}" . self::defaultStr($field) . ';',

            'time'
                => "{$pad}\$table->time('{$name}'){$null}" . self::defaultStr($field) . ';',

            'json', 'array', 'repeater'
                => "{$pad}\$table->json('{$name}'){$null};",

            default // string, text, email, url, phone, password, select, radio, etc.
                => "{$pad}\$table->string('{$name}', " . self::length($field) . "){$null}{$unique}" . self::defaultStr($field) . ';',
        };
    }

    // --------------------------------------------------------------------------
    // Helpers
    // --------------------------------------------------------------------------

    private static function length(ModuleField $field): int
    {
        return ($field->length > 0) ? (int) $field->length : 255;
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
        if (! self::hasDefault($field)) {
            return '';
        }

        return '->default(' . $field->default_value . ')';
    }
}
