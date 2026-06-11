<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generates/overwrites JSON schema files from ModuleLayout records.
 *
 * Each ModuleLayout stores a `layout_json` array of field_name strings
 * in the user's chosen order (saved by the drag-drop component).
 * This class converts those ordered field lists into the JSON configs
 * consumed by JsonFormBuilder and JsonTableBuilder.
 */
final class LayoutGenerator
{
    /**
     * Write JSON schema files for every layout defined on the module.
     *
     * @param  bool  $force  When true, overwrite files even if they already have content.
     * @return bool          True if at least one file was written.
     */
    public static function generate(Module $module, bool $force = false): bool
    {
        $model    = Str::studly($module->name);
        $resource = Str::studly(Str::plural($module->name));
        $basePath = app_path("Filament/Resources/{$resource}");

        /** @var Collection<string, ModuleField> $fieldMap field_name → ModuleField */
        $fieldMap = ModuleField::where('module_id', $module->id)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('field_name');

        /** @var Collection<int, ModuleLayout> $layouts */
        $layouts = ModuleLayout::where('module_id', $module->id)->get();

        if ($layouts->isEmpty()) {
            return false;
        }

        $wrote = false;

        foreach ($layouts as $layout) {
            $fieldNames = is_array($layout->layout_json) ? $layout->layout_json : [];

            $filePath = match ($layout->layout_type) {
                'create' => "{$basePath}/Schemas/createView.json",
                'edit'   => "{$basePath}/Schemas/editView.json",
                'detail' => "{$basePath}/Schemas/detailView.json",
                'list'   => "{$basePath}/Tables/listView.json",
                default  => null,
            };

            if ($filePath === null) {
                continue;
            }

            // Skip if file already has content and we're not forcing
            if (! $force && File::exists($filePath) && self::fileHasContent($filePath)) {
                continue;
            }

            $content = $layout->layout_type === 'list'
                ? self::buildListJson($model, $resource, $fieldNames, $fieldMap)
                : self::buildFormJson($model, $layout->layout_type, $fieldNames, $fieldMap);

            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $wrote = true;
        }

        return $wrote;
    }

    // ── JSON builders ─────────────────────────────────────────────────────────

    public static function remove(Module $module): bool
    {
        $resource = Str::studly(Str::plural($module->name));
        $basePath = app_path("Filament/Resources/{$resource}");

        $files = [
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

        $schemasPath = "{$basePath}/Schemas";
        $tablesPath  = "{$basePath}/Tables";

        if (File::isDirectory($schemasPath) && empty(File::files($schemasPath))) {
            File::deleteDirectory($schemasPath);
        }

        if (File::isDirectory($tablesPath) && empty(File::files($tablesPath))) {
            File::deleteDirectory($tablesPath);
        }

        return $removed;
    }

    /** @param Collection<string, ModuleField> $fieldMap */
    private static function buildFormJson(
        string $model,
        string $layoutType,
        array $fieldNames,
        Collection $fieldMap,
    ): array {
        $title = match ($layoutType) {
            'create' => "Create {$model}",
            'edit'   => "Edit {$model}",
            'detail' => "View {$model}",
            default  => $model,
        };

        $isDetail   = $layoutType === 'detail';
        $components = [];

        foreach ($fieldNames as $fieldName) {
            $field = $fieldMap->get($fieldName);
            if ($field === null) {
                continue;
            }

            $componentType = $isDetail
                ? self::fieldTypeToDetailComponent($field->type)
                : self::fieldTypeToFormComponent($field->type);

            $component = [
                'component' => $componentType,
                'name'      => $field->field_name,
                'label'     => $field->label,
            ];

            if (! $isDetail && $field->required) {
                $component['required'] = true;
            }

            // Select/radio: attach static options from ModuleField.options
            if (! $isDetail && in_array($field->type, ['select', 'dropdown', 'radio', 'checkboxList', 'checkbox_list'], true)) {
                $raw = is_array($field->options) ? $field->options : [];
                $opts = [];
                foreach ($raw as $opt) {
                    if (isset($opt['key'], $opt['value'])) {
                        $opts[$opt['key']] = $opt['value'];
                    }
                }
                $component['options_source'] = 'static';
                $component['options']        = $opts;
            }

            $components[] = $component;
        }

        return [
            'title'      => $title,
            'model'      => "App\\Models\\{$model}",
            'components' => $components,
        ];
    }

    /** @param Collection<string, ModuleField> $fieldMap */
    private static function buildListJson(
        string $model,
        string $resource,
        array $fieldNames,
        Collection $fieldMap,
    ): array {
        $boolTypes = ['boolean', 'toggle', 'checkbox'];

        $columns = [];
        foreach ($fieldNames as $fieldName) {
            $field = $fieldMap->get($fieldName);
            if ($field === null) {
                continue;
            }

            $isBool = in_array(strtolower($field->type), $boolTypes, true);

            $column = [
                'type'  => $isBool ? 'icon' : self::fieldTypeToColumnComponent($field->type),
                'name'  => $field->field_name,
                'label' => $field->label,
            ];

            if ($isBool) {
                $column['boolean'] = true;
            }

            if ($field->searchable) {
                $column['searchable'] = true;
            }

            if ($field->sortable) {
                $column['sortable'] = true;
            }

            $columns[] = $column;
        }

        return [
            'title'   => $resource,
            'model'   => "App\\Models\\{$model}",
            'columns' => $columns,
            'filters' => [],
            'actions' => [
                ['type' => 'edit',   'label' => 'Edit',    'ui' => ['icon' => 'heroicon-m-pencil-square', 'hiddenLabel' => true, 'iconButton' => true, 'tooltip' => 'Edit']],
                ['type' => 'view',   'label' => 'Details', 'ui' => ['icon' => 'heroicon-o-eye',           'hiddenLabel' => true, 'iconButton' => true, 'tooltip' => 'Details']],
            ],
        ];
    }

    // ── Type maps ─────────────────────────────────────────────────────────────

    /** Maps a ModuleField type to the JsonFormBuilder 'component' name for create/edit forms. */
    private static function fieldTypeToFormComponent(string $type): string
    {
        return match (strtolower($type)) {
            'textarea', 'longtext', 'richtext'          => 'textarea',
            'boolean', 'toggle'                         => 'toggle',
            'checkbox'                                  => 'checkbox',
            'date'                                      => 'datePicker',
            'datetime', 'timestamp'                     => 'dateTimePicker',
            'select', 'dropdown'                        => 'select',
            'radio'                                     => 'radio',
            'checkboxList', 'checkbox_list'             => 'checkboxList',
            'fileUpload', 'file', 'image'               => 'fileUpload',
            'json', 'array', 'repeater'                 => 'textarea',
            default                                     => 'textInput',
        };
    }

    /** Maps a ModuleField type to the JsonFormBuilder 'component' name for detail/view forms. */
    private static function fieldTypeToDetailComponent(string $type): string
    {
        return match (strtolower($type)) {
            'boolean', 'toggle', 'checkbox'             => 'toggle',
            default                                     => 'textEntry',
        };
    }

    /** Maps a ModuleField type to the JsonTableBuilder 'type' name for list columns. */
    private static function fieldTypeToColumnComponent(string $type): string
    {
        return match (strtolower($type)) {
            'date'                                      => 'text',
            'datetime', 'timestamp'                     => 'text',
            default                                     => 'text',
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns true when the JSON file already has a non-empty components/columns array. */
    private static function fileHasContent(string $path): bool
    {
        $raw = File::get($path);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return false;
        }

        $items = $data['components'] ?? $data['columns'] ?? [];

        return ! empty($items);
    }
}
