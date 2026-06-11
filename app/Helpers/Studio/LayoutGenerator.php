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
        $model = Str::studly($module->name);
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
            $rawJson = is_array($layout->layout_json) ? $layout->layout_json : [];
            // Normalize to sections format for unified processing
            $sections = self::normalizeSections($rawJson);
            // Flat list of all field names (used for list/table view)
            $fieldNames = collect($sections)->flatMap(fn ($s) => $s['fields'] ?? [])->values()->all();

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
                : self::buildFormJson($model, $layout->layout_type, $sections, $fieldMap);

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

    /**
     * @param  array<int, array{title: string, columns: int, fields: string[]}>  $sections
     * @param  Collection<string, ModuleField>  $fieldMap
     */
    private static function buildFormJson(
        string $model,
        string $layoutType,
        array $sections,
        Collection $fieldMap,
    ): array {
        $title = match ($layoutType) {
            'create' => "Create {$model}",
            'edit'   => "Edit {$model}",
            'detail' => "View {$model}",
            default  => $model,
        };

        $isDetail = $layoutType === 'detail';

        $components = [];

        foreach ($sections as $section) {
            $sectionTitle = $section['title'] ?? 'General';
            $sectionColumns = $section['columns'] ?? 2;
            $fieldNames = $section['fields'] ?? [];

            $sectionFields = [];
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

                // Attach static options for select/radio/checkboxList fields
                if (! $isDetail && in_array($field->type, ['select', 'dropdown', 'enum', 'radio', 'checkboxList', 'checkbox_list'], true)) {
                    $modulename= Str::snake($model);
                    $dropdownName = "{$modulename}_{$field->field_name}_dom";
                    $component['options_source'] = 'helper';
                    $component['helper_class']   = 'App\\Helpers\\Studio\\DropdownHandler';
                    $component['helper_method']  = 'get';
                    $component['helper_params']  = [
                    $dropdownName
                    ];
                }

                $sectionFields[] = $component;
            }

            if (empty($sectionFields)) {
                continue;
            }

            $components[] = [
                'component' => 'section',
                'label' => $sectionTitle,
                'columns' => $sectionColumns,
                'collapsible' => false,
                'columnSpan' => 'full',
                'schema' => $sectionFields,
            ];
        }

        return [
            'title' => $title,
            'model' => "App\\Models\\{$model}",
            'components' => $components,
        ];
    }

    /**
     * Normalize layout_json to the canonical sections array format.
     *
     * Supports:
     *  - New format: [{"title":"...","columns":2,"fields":[...]}]
     *  - Legacy flat format: ["field1","field2"]
     *  - Empty / null → returns one empty default section.
     *
     * @param  array<mixed>  $raw
     * @return array<int, array{title: string, columns: int, fields: string[]}>
     */
    private static function normalizeSections(array $raw): array
    {
        if (empty($raw)) {
            return [['title' => 'General', 'columns' => 2, 'fields' => []]];
        }

        // Already sections format: first element is an associative array with a 'fields' key
        if (isset($raw[0]) && is_array($raw[0]) && array_key_exists('fields', $raw[0])) {
            return array_map(fn ($s) => [
                'title' => $s['title'] ?? 'Section',
                'columns' => isset($s['columns']) ? (is_numeric($s['columns']) ? (int) $s['columns'] : $s['columns']) : 2,
                'fields' => array_values(array_filter((array) ($s['fields'] ?? []), 'is_string')),
            ], $raw);
        }

        // Legacy flat array of strings
        $fields = array_values(array_filter($raw, 'is_string'));

        return [[
            'title' => 'General',
            'columns' => 2,
            'fields' => $fields,
        ]];
    }

    /** @param Collection<string, ModuleField> $fieldMap */
    private static function buildListJson(
        string $model,
        string $resource,
        array $fieldNames,
        Collection $fieldMap,
    ): array {
        $boolTypes = ['boolean', 'toggle', 'checkbox'];
        $columns   = [];

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
                [ 
                    "type"=> "group",
                    "label"=> "More",
                    "icon"=> "heroicon-o-ellipsis-horizontal",
                    "items"=> [
                        ['type' => 'delete',   'label' => 'Delete']
                    ]
                ],
            ],
            "record_actions_position"=> "BeforeColumns"
        ];
    }

    // ── Type maps ─────────────────────────────────────────────────────────────

    /** Filament form component name for create/edit views. */
    private static function fieldTypeToFormComponent(string $type): string
    {
        return match (strtolower($type)) {
            'textarea', 'longtext', 'richtext'              => 'textarea',
            'boolean', 'toggle'                             => 'toggle',
            'checkbox'                                      => 'checkbox',
            'date'                                          => 'datePicker',
            'datetime', 'timestamp'                         => 'dateTimePicker',
            'select', 'dropdown', 'enum'                    => 'select',
            'radio'                                         => 'radio',
            'checkboxList', 'checkbox_list'                 => 'checkboxList',
            'fileUpload', 'file', 'image'                   => 'fileUpload',
            'json', 'array', 'repeater'                     => 'textarea',
            default                                         => 'textInput',
        };
    }

    /** Filament infolist component name for detail/view. */
    private static function fieldTypeToDetailComponent(string $type): string
    {
        return match (strtolower($type)) {
            'boolean', 'toggle', 'checkbox'                 => 'toggle',
            default                                         => 'textEntry',
        };
    }

    /** JsonTableBuilder column type for list view (non-boolean columns). */
    private static function fieldTypeToColumnComponent(string $type): string
    {
        return 'text';
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
