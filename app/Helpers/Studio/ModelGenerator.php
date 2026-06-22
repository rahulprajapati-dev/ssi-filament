<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
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
        $name  = (string) $module->name;
        $model = Str::studly($name);
        $table = Str::snake(Str::plural($name));
        $path  = app_path("Models/{$model}.php");

        if (File::exists($path)) {
            return false;
        }

        $content = StubRenderer::render('Model.stub', [
            'MODEL'           => $model,
            'TABLE'           => $table,
            'RESOURCE'        => Str::studly(Str::plural($name)),
            'PLURAL_RESOURCE' => Str::studly(Str::plural($name)),
            'UUID_ROUTE_KEY'  => self::buildRouteKeyMethod($module),
            'RELATIONSHIPS'   => self::buildRelationshipMethods($module),
            'FIELD_CASTS'     => self::buildFieldCasts($module),
        ]);

        File::ensureDirectoryExists(app_path('Models'));
        File::put($path, $content);

        return true;
    }

    /**
     * Sync studio-managed regions (route-key + relationships) in an existing model file.
     * Only the content inside the region markers is replaced — all custom code is preserved.
     * Returns true when the file was updated, false when unchanged or markers are absent.
     */
    public static function sync(Module $module): bool
    {
        $model = Str::studly((string) $module->name);
        $path  = app_path("Models/{$model}.php");

        if (! File::exists($path)) {
            return self::generate($module);
        }

        $content = File::get($path);
        $changed = false;

        $regions = [
            'studio-route-key'     => self::buildRouteKeyMethod($module),
            'studio-relationships' => self::buildRelationshipMethods($module),
            'studio-casts'         => self::buildFieldCasts($module),
        ];

        foreach ($regions as $region => $newContent) {
            $updated = preg_replace_callback(
                '/(?m)^(\s*\/\/ region:' . preg_quote($region, '/') . '[^\n]*\n).*?([ \t]*\/\/ endregion:' . preg_quote($region, '/') . ')/s',
                fn (array $m) => $m[1] . $newContent . '    // endregion:' . $region,
                $content,
            );

            if ($updated !== null && $updated !== $content) {
                $content = $updated;
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        File::put($path, $content);

        return true;
    }

    private static function buildRouteKeyMethod(Module $module): string
    {
        if (! $module->use_uuid) {
            return '';
        }

        return implode("\n", [
            '',
            '    public function getRouteKeyName(): string',
            '    {',
            "        return 'uuid';",
            '    }',
            '',
        ]);
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

    /**
     * Build cast entries for fields that need Eloquent casting.
     * - is_multiple select/file/image → 'array' (stored as JSON)
     * - json/array/repeater types     → 'array'
     */
    private static function buildFieldCasts(Module $module): string
    {
        $fields = ModuleField::where('module_id', $module->id)
            ->orderBy('sort_order')
            ->get();

        $lines = [];
        foreach ($fields as $field) {
            $needsArrayCast =
                (! empty($field->is_multiple) && in_array($field->type, ['select', 'dropdown', 'enum', 'file', 'image', 'fileupload'], true))
                || in_array($field->type, ['json', 'array', 'repeater'], true);

            if ($needsArrayCast) {
                $lines[] = "        '{$field->field_name}' => 'array',";
            }
        }

        return empty($lines) ? '' : implode("\n", $lines) . "\n";
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
