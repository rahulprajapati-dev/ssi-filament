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
            'RELATIONSHIPS'   => self::buildRelationshipMethods($module),
        ]);

        File::ensureDirectoryExists(app_path('Models'));
        File::put($path, $content);

        return true;
    }

    private static function buildRelationshipMethods(Module $module): string
    {
        $relationships = $module->relationships_json;

        if (empty($relationships) || ! is_array($relationships)) {
            return '';
        }

        $methods = [];

        foreach ($relationships as $rel) {
            $type          = $rel['type']          ?? '';
            $relatedModule = $rel['related_module'] ?? '';

            if (! $type || ! $relatedModule) {
                continue;
            }

            $relatedModel = 'App\\Models\\' . Str::studly(Str::singular($relatedModule));
            $methodName   = ! empty($rel['name'])
                ? $rel['name']
                : self::guessMethodName($type, $relatedModule);
            $foreignKey   = $rel['foreign_key'] ?? '';
            $fkArg        = $foreignKey ? ", '{$foreignKey}'" : '';

            $methods[] = implode("\n", [
                "    public function {$methodName}()",
                '    {',
                "        return \$this->{$type}(\\{$relatedModel}::class{$fkArg});",
                '    }',
            ]);
        }

        if (empty($methods)) {
            return '';
        }

        return "\n" . implode("\n\n", $methods) . "\n";
    }

    private static function guessMethodName(string $type, string $relatedModule): string
    {
        $base = Str::camel($relatedModule);

        return match ($type) {
            'hasMany', 'belongsToMany', 'hasManyThrough' => Str::plural($base),
            default                                      => Str::singular($base),
        };
    }

    public static function remove(Module $module): bool
    {
        $model = Str::studly((string) $module->name);
        $path  = app_path("Models/{$model}.php");

        if (! File::exists($path)) {
            return false;
        }
        File::delete($path);
        return true;
    }
}
