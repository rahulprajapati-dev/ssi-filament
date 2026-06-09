<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generates a production-ready Eloquent model from Model.stub.
 *
 * Returns:
 *   true  — model file written
 *   false — model file already exists (skipped to protect custom code)
 */
final class ModelGenerator
{
    public static function generate(Module $module): bool
    {
        $name     = (string) $module->name;
        $model    = Str::studly($name);
        $resource = Str::studly(Str::plural($name));
        $table    = Str::snake(Str::plural($name));
        $path     = app_path("Models/{$model}.php");

        if (File::exists($path)) {
            return false;
        }

        $content = StubRenderer::render('Model.stub', [
            'MODEL'           => $model,
            'TABLE'           => $table,
            'RESOURCE'        => $resource,
            'PLURAL_RESOURCE' => $resource,
        ]);

        File::ensureDirectoryExists(app_path('Models'));
        File::put($path, $content);

        return true;
    }
}
