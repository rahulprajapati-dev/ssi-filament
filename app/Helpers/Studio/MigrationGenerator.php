<?php

namespace App\Helpers\Studio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Module;

class MigrationGenerator
{
    public static function generate(Module $module): void
    {
        $table = Str::snake(Str::plural($module->name));

        $file = database_path(
            'migrations/' . date('Y_m_d_His') . "_create_{$table}_table.php"
        );

        if (File::exists($file)) return;

        File::put($file, self::buildMigration($module, $table));
    }

    private static function buildMigration(Module $module, string $table): string
    {
        $fields = $module->fields()->orderBy('sort_order')->get();

        $columns = [];
        foreach ($fields as $field) {
            $columns[] = self::buildColumn($field);
        }

        $columnLines = implode("\n", $columns);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
{$columnLines}
            \$table->string('created_by', 36)->nullable();
            \$table->string('updated_by', 36)->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
    }

    private static function buildColumn($field): string
    {
        $name     = $field->field_name;
        $nullable = $field->required ? '' : '->nullable()';
        $unique   = $field->unique_field ? '->unique()' : '';
        $default  = $field->default_value !== null && $field->default_value !== ''
            ? "->default(" . self::defaultValue($field->type, $field->default_value) . ")"
            : '';
        $indent   = '            ';

        switch ($field->type) {
            case 'textarea':
            case 'longtext':
                return "{$indent}\$table->text('{$name}'){$nullable}{$default};";

            case 'integer':
            case 'number':
                return "{$indent}\$table->integer('{$name}'){$nullable}{$unique}{$default};";

            case 'decimal':
            case 'float':
                return "{$indent}\$table->decimal('{$name}', 15, 4){$nullable}{$unique}{$default};";

            case 'boolean':
            case 'toggle':
            case 'checkbox':
                $def = $field->default_value !== null ? "->default((bool) {$field->default_value})" : '->default(false)';
                return "{$indent}\$table->boolean('{$name}'){$def};";

            case 'date':
                return "{$indent}\$table->date('{$name}'){$nullable}{$default};";

            case 'datetime':
            case 'timestamp':
                return "{$indent}\$table->dateTime('{$name}'){$nullable}{$default};";

            case 'json':
            case 'repeater':
                return "{$indent}\$table->json('{$name}'){$nullable};";

            default:
                // text, string, email, url, password, phone, select, radio, etc.
                $length = ($field->length > 0) ? (int) $field->length : 255;
                return "{$indent}\$table->string('{$name}', {$length}){$nullable}{$unique}{$default};";
        }
    }

    private static function defaultValue(string $type, string $value): string
    {
        return in_array($type, ['integer', 'number', 'decimal', 'float'])
            ? $value
            : "'" . addslashes($value) . "'";
    }
}
