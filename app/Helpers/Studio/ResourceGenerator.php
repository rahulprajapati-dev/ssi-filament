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
        $model    = Str::studly((string) $module->fullname);
        $resource = Str::pluralStudly($model);
        $icon     = (string) ($module->icon ?: self::DEFAULT_ICON);

        $basePath = app_path("Filament/Resources/{$resource}");

        File::ensureDirectoryExists($basePath);
        File::ensureDirectoryExists("{$basePath}/Pages");
        File::ensureDirectoryExists("{$basePath}/Schemas");
        File::ensureDirectoryExists("{$basePath}/Tables");
        File::ensureDirectoryExists("{$basePath}/CustomSchemas");
        File::ensureDirectoryExists("{$basePath}/CustomTables");

        if (! File::exists("{$basePath}/CustomSchemas/README.md")) {
            File::put("{$basePath}/CustomSchemas/README.md", self::customSchemasReadme());
        }
        if (! File::exists("{$basePath}/CustomTables/README.md")) {
            File::put("{$basePath}/CustomTables/README.md", self::customTablesReadme());
        }

        // [L10] Build RESOURCE_SLUG with fallback so an empty plural_label never
        // produces a blank slug. [H12] Null-guard plural/singular labels.
        $slug = Str::slug($module->plural_label ?? '');
        if (empty($slug)) {
            $slug = Str::slug($module->name ?? 'module');
        }

        $vars = [
            'MODEL'             => $model,
            'MODEL_LOWER'       => $module->name, // must match Module::where('name',...) in ModuleState::active()
            'RESOURCE'          => $resource,
            'RESOURCE_SINGULAR' => $module->singular_label ?? '', // [H12] null-safe
            'RESOURCE_PLURAL'   => $module->plural_label ?? '',   // [H12] null-safe
            'RESOURCE_SLUG'     => $slug,
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

    public static function remove(Module $module, bool $isCustom = false): bool
    {
        $model    = Str::studly((string) $module->fullname);
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

        // Always wipe Custom* dirs entirely on uninstall (developer overrides go with the module).
        foreach (["{$basePath}/CustomSchemas", "{$basePath}/CustomTables"] as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
                $removed = true;
            }
        }

        foreach (["{$basePath}/Pages", "{$basePath}/Schemas", "{$basePath}/Tables"] as $dir) {
            if (File::isDirectory($dir)
                && empty(File::files($dir))
                && empty(File::directories($dir))
            ) {
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

    /**
     * Force-regenerate only the JSON-loading glue files (Form.php, Table.php).
     *
     * These are Studio-owned thin wrappers — they are the only PHP files that
     * load the JSON schema at runtime and must reflect the current stub logic
     * (e.g. CustomSchemas/ priority check). Developers should never customise
     * these files directly; they should copy JSON into CustomSchemas/ instead.
     *
     * Called by StudioManager::runRebuild() so that modules deployed before the
     * custom-path-check was added to the stubs are automatically upgraded.
     */
    public static function regenerateGlueFiles(Module $module): bool
    {
        $model    = Str::studly((string) $module->fullname);
        $resource = Str::pluralStudly($model);
        $basePath = app_path("Filament/Resources/{$resource}");

        if (! File::isDirectory($basePath)) {
            return false;
        }

        $slug = Str::slug($module->plural_label ?? '');
        if (empty($slug)) {
            $slug = Str::slug($module->name ?? 'module');
        }

        $vars = [
            'MODEL'             => $model,
            'MODEL_LOWER'       => $module->name,
            'RESOURCE'          => $resource,
            'RESOURCE_SINGULAR' => $module->singular_label ?? '',
            'RESOURCE_PLURAL'   => $module->plural_label ?? '',
            'RESOURCE_SLUG'     => $slug,
            'ICON'              => (string) ($module->icon ?: self::DEFAULT_ICON),
        ];

        $glue = [
            "{$basePath}/Schemas/{$model}Form.php"    => 'Form.stub',
            "{$basePath}/Tables/{$resource}Table.php" => 'Table.stub',
        ];

        foreach ($glue as $destination => $stub) {
            File::put($destination, StubRenderer::render($stub, $vars));
        }

        return true;
    }

    private static function customSchemasReadme(): string
    {
        return <<<'TEXT'
# CustomSchemas

Your customization zone for form and detail layouts.
Studio never modifies files here — they survive every Repair & Rebuild.

Supported overrides (copy the file from ../Schemas/ into this folder and edit):
  createView.json  — Create form layout
  editView.json    — Edit form layout
  detailView.json  — View / detail layout
  default.json     — Fallback layout

A file placed here takes priority over the Studio-generated version in ../Schemas/ at runtime.
TEXT;
    }

    private static function customTablesReadme(): string
    {
        return <<<'TEXT'
# CustomTables

Your customization zone for the list table.
Studio never modifies files here — they survive every Repair & Rebuild.

Supported overrides (copy the file from ../Tables/ into this folder and edit):
  listView.json  — Table columns, filters, and actions

A file placed here takes priority over the Studio-generated version in ../Tables/ at runtime.
TEXT;
    }
}
