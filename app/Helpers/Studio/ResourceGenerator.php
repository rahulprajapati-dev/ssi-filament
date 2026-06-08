<?php

namespace App\Helpers\Studio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Module;

class ResourceGenerator
{
    public static function generate(Module $module): void
    {
        $model = Str::studly($module->name);
        $resource = "{$model}Resource";

        $path = app_path("Filament/Resources/{$resource}.php");

        if (File::exists($path)) return;

        File::put($path, self::stub($model, $resource));
    }

    private static function stub($model, $resource): string
    {
        return <<<PHP
<?php

namespace App\Filament\Resources;

use App\Models\\{$model};
use Filament\Resources\Resource;

class {$resource} extends Resource
{
    protected static ?string \$model = {$model}::class;
}
PHP;
    }
}