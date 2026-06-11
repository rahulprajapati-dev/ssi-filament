<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generates all Filament resource PHP files and JSON schema stubs for a module.
 *
 * Returns:
 *   true  — at least one file was written
 *   false — all files already existed (idempotent skip)
 */
final class ResourceGenerator
{
    private const DEFAULT_ICON = 'heroicon-o-rectangle-stack';

    public static function generate(Module $module): bool
    {
        $model    = Str::studly((string) $module->name);
        $resource = Str::pluralStudly($model);
        $icon     = (string) ($module->icon ?: self::DEFAULT_ICON);

        $basePath = app_path("Filament/Resources/{$resource}");

        File::ensureDirectoryExists($basePath);
        File::ensureDirectoryExists("{$basePath}/Pages");
        File::ensureDirectoryExists("{$basePath}/Schemas");
        File::ensureDirectoryExists("{$basePath}/Tables");

        $vars = [
            'MODEL'             => $model,
            'RESOURCE'          => $resource,
            'RESOURCE_SINGULAR' => Str::singular($resource),
            'ICON'              => $icon,
        ];

        $files = [
            "{$basePath}/{$model}Resource.php"        => 'Resource.stub',
            "{$basePath}/Pages/List{$resource}.php"   => 'ListPage.stub',
            "{$basePath}/Pages/Create{$model}.php"    => 'CreatePage.stub',
            "{$basePath}/Pages/Edit{$model}.php"      => 'EditPage.stub',
            "{$basePath}/Pages/View{$model}.php"      => 'ViewPage.stub',
            "{$basePath}/Schemas/{$model}Form.php"    => 'Form.stub',
            "{$basePath}/Tables/{$resource}Table.php" => 'Table.stub',
            "{$basePath}/Schemas/default.json"        => 'default.json.stub',
            "{$basePath}/Schemas/createView.json"     => 'createView.json.stub',
            "{$basePath}/Schemas/editView.json"       => 'editView.json.stub',
            "{$basePath}/Schemas/detailView.json"     => 'detailView.json.stub',
            "{$basePath}/Tables/listView.json"        => 'listView.json.stub',
        ];

        $wrote = false;

        foreach ($files as $destination => $stub) {
            if (File::exists($destination)) {
                continue;
            }

            File::put($destination, StubRenderer::render($stub, $vars));
            $wrote = true;
        }

        return $wrote;
    }

    public static function remove(Module $module): bool
    {
        $model    = Str::studly((string) $module->name);
        $resource = Str::pluralStudly($model);
        $basePath = app_path("Filament/Resources/{$resource}");

        $files = [
            "{$basePath}/{$model}Resource.php",
            "{$basePath}/Pages/List{$resource}.php",
            "{$basePath}/Pages/Create{$model}.php",
            "{$basePath}/Pages/Edit{$model}.php",
            "{$basePath}/Pages/View{$model}.php",
            "{$basePath}/Schemas/{$model}Form.php",
            "{$basePath}/Tables/{$resource}Table.php",
            "{$basePath}/Schemas/default.json",
            "{$basePath}/Schemas/createView.json",
            "{$basePath}/Schemas/editView.json",
            "{$basePath}/Schemas/detailView.json",
            "{$basePath}/Tables/listView.json",
        ];

        $removed = false;

        foreach ($files as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $removed = true;
            }
        }

        foreach (["{$basePath}/Pages", "{$basePath}/Schemas", "{$basePath}/Tables"] as $dir) {
            if (File::isDirectory($dir) && empty(File::files($dir))) {
                File::deleteDirectory($dir);
            }
        }

        if (File::isDirectory($basePath)
            && empty(File::files($basePath))
            && empty(File::directories($basePath))
        ) {
            File::deleteDirectory($basePath);
        }

        return $removed;
    }
}
