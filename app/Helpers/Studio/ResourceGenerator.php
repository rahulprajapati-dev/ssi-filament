<?php

namespace App\Helpers\Studio;

use App\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResourceGenerator
{
    public static function generate(Module $module): void
    {
        $model = Str::studly($module->name);
        $icon = $module->icon;
        $resource = Str::pluralStudly($model);

        $basePath = app_path("Filament/Resources/{$resource}");

        File::ensureDirectoryExists($basePath);
        File::ensureDirectoryExists("{$basePath}/Pages");
        File::ensureDirectoryExists("{$basePath}/Schemas");
        File::ensureDirectoryExists("{$basePath}/Tables");

        $files = [
            "{$basePath}/{$model}Resource.php" => 'Resource.stub',

            "{$basePath}/Pages/List{$resource}.php" => 'ListPage.stub',
            "{$basePath}/Pages/Create{$model}.php" => 'CreatePage.stub',
            "{$basePath}/Pages/Edit{$model}.php" => 'EditPage.stub',
            "{$basePath}/Pages/View{$model}.php" => 'ViewPage.stub',

            "{$basePath}/Schemas/{$model}Form.php" => 'Form.stub',

            "{$basePath}/Tables/{$resource}Table.php" => 'Table.stub',

            "{$basePath}/Schemas/default.json" => 'default.json.stub',
            "{$basePath}/Schemas/createView.json" => 'createView.json.stub',
            "{$basePath}/Schemas/editView.json" => 'editView.json.stub',
            "{$basePath}/Schemas/detailView.json" => 'detailView.json.stub',

            "{$basePath}/Tables/listView.json" => 'listView.json.stub',
        ];

        foreach ($files as $destination => $stub) {
            if (File::exists($destination)) {
                continue;
            }

            File::put(
                $destination,
                self::renderStub($stub, [
                    'MODEL' => $model,
                    'RESOURCE' => $resource,
                    'RESOURCE_SINGULAR' => Str::singular($resource),
                    'ICON' => $icon,
                ])
            );
        }
    }

    public static function remove(Module $module): bool
    {
        $model = Str::studly($module->name);
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

        // Remove empty directories
        foreach ([
            "{$basePath}/Pages",
            "{$basePath}/Schemas",
            "{$basePath}/Tables",
        ] as $dir) {
            if (File::isDirectory($dir) && empty(File::files($dir))) {
                File::deleteDirectory($dir);
            }
        }

        // Remove resource directory if empty
        if (File::isDirectory($basePath) && empty(File::files($basePath)) && empty(File::directories($basePath))) {
            File::deleteDirectory($basePath);
        }

        return $removed;
    }

    protected static function renderStub(string $stub, array $replace): string
    {
        $content = File::get(
            app_path("Helpers/Studio/Stubs/{$stub}")
        );

        foreach ($replace as $key => $value) {
            $content = str_replace(
                "{{{$key}}}",
                $value,
                $content
            );
        }

        return $content;
    }
}