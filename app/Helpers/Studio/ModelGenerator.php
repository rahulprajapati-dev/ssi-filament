<?php
namespace App\Helpers\Studio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Module;

class ModelGenerator
{
    public static function generate(Module $module): void
    {
        $model = Str::studly($module->name);
        $path = app_path("Models/{$model}.php");

        if (File::exists($path)) return;

        File::put($path, self::stub($module));
    }

    private static function stub(string $module): string
    {

        $model = Str::studly($module->name);

        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModuleHookTrait;
use App\Traits\HasCreatedBy;

class {$model} extends Model
{
    use ModuleHookTrait;
    use HasCreatedBy;
    protected \$guarded = [];
}
PHP;
    }
}