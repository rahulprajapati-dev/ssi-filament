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
            "migrations/" . date('Y_m_d_His') . "_create_{$table}_table.php"
        );

        if (File::exists($file)) return;

        File::put($file, self::stub($table));
    }

    private static function stub(string $table): string
    {
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
            \$table->string('name');
            \$table->string('created_by', 10)->nullable();
            \$table->string('updated_by', 10)->nullable();
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
}