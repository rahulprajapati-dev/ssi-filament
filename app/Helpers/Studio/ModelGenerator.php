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
        $name  = (string) $module->fullname;
        $model = Str::studly($name);
        $table = strtolower($module->table);
        $vars  = self::buildVars($module, $model, $table);

        File::ensureDirectoryExists(app_path('Models/Studio'));
        File::ensureDirectoryExists(app_path('Models'));

        // Base class — Studio-owned, always written.
        File::put(
            app_path("Models/Studio/Base{$model}.php"),
            StubRenderer::render('BaseModel.stub', $vars),
        );

        // Developer extension — written once, never overwritten.
        $devPath = app_path("Models/{$model}.php");
        if (! File::exists($devPath)) {
            File::put($devPath, StubRenderer::render('Model.stub', $vars));
            return true;
        }

        return false;
    }

    /**
     * Regenerate the Studio-managed base class.
     *
     * For modules still on the old single-file style (file contains region markers),
     * also applies the legacy regex sync so their developer model stays consistent
     * until they are fully rebuilt with the new base/extension pattern.
     */
    public static function sync(Module $module): bool
    {
        $name  = (string) $module->fullname;
        $model = Str::studly($name);
        $table = strtolower($module->table);
        $vars  = self::buildVars($module, $model, $table);

        File::ensureDirectoryExists(app_path('Models/Studio'));

        // Always regenerate the base class.
        File::put(
            app_path("Models/Studio/Base{$model}.php"),
            StubRenderer::render('BaseModel.stub', $vars),
        );

        $devPath = app_path("Models/{$model}.php");

        if (! File::exists($devPath)) {
            // No developer model at all — generate the thin extension.
            File::put($devPath, StubRenderer::render('Model.stub', $vars));
            return true;
        }

        $content = File::get($devPath);

        // New-style model already extends the base — nothing more to do.
        if (str_contains($content, "extends Base{$model}")) {
            return true;
        }

        // Old-style single-file model — apply the legacy region sync so the
        // developer's file stays consistent while it hasn't been migrated yet.
        $changed = false;
        $regions = [
            'studio-route-key'     => self::buildRouteKeyMethod($module),
            'studio-relationships' => self::buildRelationshipMethods($module),
            'studio-casts'         => self::buildFieldCasts($module),
        ];

        foreach ($regions as $region => $newContent) {
            $updated = preg_replace_callback(
                '/(?m)^(\s*\/\/ region:' . preg_quote($region, '/') . '[^\n]*\n).*?([ \t]*\/\/ endregion:' . preg_quote($region, '/') . ')/s',
                fn (array $m) => $m[1] . $newContent . $m[2],
                $content,
            );

            if ($updated !== null && $updated !== $content) {
                $content = $updated;
                $changed = true;
            }
        }

        if ($changed) {
            File::put($devPath, $content);
        }

        return $changed;
    }

    private static function buildVars(Module $module, string $model, string $table): array
    {
        return [
            'MODEL'           => $model,
            'TABLE'           => $table,
            'RESOURCE'        => Str::studly(Str::plural((string) $module->fullname)),
            'PLURAL_RESOURCE' => Str::studly(Str::plural((string) $module->fullname)),
            'UUID_ROUTE_KEY'  => self::buildRouteKeyMethod($module),
            'RELATIONSHIPS'   => self::buildRelationshipMethods($module),
            'FIELD_CASTS'     => self::buildFieldCasts($module),
        ];
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

            // [M6] Validate the derived class name is a legal PHP identifier before use.
            $relatedClass = Str::studly(Str::singular($relatedModule));
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $relatedClass)) {
                continue;
            }
            $relatedModel = 'App\\Models\\' . $relatedClass;
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
                || in_array($field->type, ['json', 'array', 'repeater', 'checkbox_list', 'checkboxlist', 'tags'], true);

            if ($needsArrayCast) {
                $lines[] = "        '{$field->field_name}' => 'array',";
            }
        }

        return empty($lines) ? '' : implode("\n", $lines) . "\n";
    }

    public static function remove(Module $module, bool $isCustom = false): bool
    {
        $model   = Str::studly((string) $module->fullname);
        $deleted = false;

        // Developer extension model.
        $devPath = app_path("Models/{$model}.php");
        if (File::exists($devPath)) {
            File::delete($devPath);
            $deleted = true;
        }

        // Studio-managed base class.
        $basePath = app_path("Models/Studio/Base{$model}.php");
        if (File::exists($basePath)) {
            File::delete($basePath);
            $deleted = true;
        }

        // Remove Studio/ dir if it is now empty.
        $studioDir = app_path('Models/Studio');
        if (is_dir($studioDir) && self::isEmptyDirectory($studioDir)) {
            if (! @rmdir($studioDir)) {
                \Illuminate\Support\Facades\Log::warning('ModelGenerator: rmdir failed', ['path' => $studioDir]);
            }
        }

        return $deleted;
    }

    private static function isEmptyDirectory(string $dir): bool
    {
        return count(array_diff(scandir($dir), ['.', '..'])) === 0;
    }
}
