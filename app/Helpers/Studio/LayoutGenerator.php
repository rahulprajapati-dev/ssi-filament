<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Helpers\Studio\FieldTypeMap;

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
        $model = Str::studly((string) $module->fullname);
        // [L21] Use Str::pluralStudly($model) — identical formula to ResourceGenerator::generate()
        // which does Str::pluralStudly($model).  Str::studly(Str::plural($module->fullname))
        // can diverge for multi-word snake_case fullnames because pluralisation happens before
        // studly-casing rather than on the already-studly-cased last word.
        $resource = Str::pluralStudly($model);
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
            // Normalize to containers format for unified processing
            $containers = self::normalizeContainers($rawJson);
            // Flat list of all field names (used for list/table view); tabs nest fields one level deeper
            $fieldNames = collect($containers)->flatMap(function ($c) {
                if (($c['type'] ?? 'section') === 'tabs') {
                    return collect($c['tabs'] ?? [])->flatMap(fn($t) => $t['fields'] ?? []);
                }
                return $c['fields'] ?? [];
            })->values()->all();

            $filePath = match ($layout->layout_type) {
                'create' => "{$basePath}/Schemas/createView.json",
                'edit' => "{$basePath}/Schemas/editView.json",
                'detail' => "{$basePath}/Schemas/detailView.json",
                'list' => "{$basePath}/Tables/listView.json",
                default => null,
            };

            if ($filePath === null) {
                continue;
            }

            // Skip if file already has content and we're not forcing
            if (!$force && File::exists($filePath) && self::fileHasContent($filePath)) {
                continue;
            }

            // When force-regenerating (rebuild), preserve manual edits that are newer
            // than the last time this layout record was saved in the database.
            // if ($force && File::exists($filePath)) {
            //     $layoutUpdatedAt = $layout->updated_at?->timestamp ?? 0;
            //     $fileModifiedAt = (int) filemtime($filePath);
            //     if ($fileModifiedAt >= $layoutUpdatedAt) {
            //         continue;
            //     }
            // }

            $content = $layout->layout_type === 'list'
                ? self::buildListJson($model, $resource, (string) $module->fullname, $fieldNames, $fieldMap, $layout->filters_json ?? [])
                : self::buildFormJson($module, $model, $layout->layout_type, $containers, $fieldMap);

            File::ensureDirectoryExists(dirname($filePath));
            File::put($filePath, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $wrote = true;
        }

        return $wrote;
    }

    // ── JSON builders ─────────────────────────────────────────────────────────

    public static function remove(Module $module): bool
    {
        // [L21] Match ResourceGenerator's formula: studly first, then pluralStudly.
        $model = Str::studly((string) $module->fullname);
        $resource = Str::pluralStudly($model);
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
        $tablesPath = "{$basePath}/Tables";

        if (
            File::isDirectory($schemasPath)
            && empty(File::files($schemasPath))
            && empty(File::directories($schemasPath))
        ) {
            File::deleteDirectory($schemasPath);
        }

        if (
            File::isDirectory($tablesPath)
            && empty(File::files($tablesPath))
            && empty(File::directories($tablesPath))
        ) {
            File::deleteDirectory($tablesPath);
        }

        return $removed;
    }

    /**
     * @param  array<int, array{type: string, ...}>  $containers
     * @param  Collection<string, ModuleField>  $fieldMap
     */
    private static function buildFormJson(
        Module $module,
        string $model,
        string $layoutType,
        array $containers,
        Collection $fieldMap,
    ): array {
        $label = $module->singular_label ?: $model;
        $title = match ($layoutType) {
            'create' => "Create {$label}",
            'edit' => "Edit {$label}",
            'detail' => "View {$label}",
            default => $label,
        };

        $isDetail = $layoutType === 'detail';

        $components = [];
        $clearOnUpdateMap = [];
        // Collect field names that appear in any visibility condition across all fields
        $reactiveFields = [];
        foreach ($fieldMap as $f) {
            $conds = $f->visibility_conditions ?? [];
            foreach ($conds as $c) {
                if (isset($c['field'])) {
                    $reactiveFields[] = $c['field'];
                }
            }
            if (!empty($f->dependent_field)) {
                $reactiveFields[] = $f->dependent_field;
                $clearOnUpdateMap[$f->dependent_field][] = $f->field_name;
            }
        }
        $reactiveFields = array_unique($reactiveFields);

        foreach ($containers as $container) {
            $type = $container['type'] ?? 'section';

            if ($type === 'tabs') {
                $tabColumns = (int) ($container['columns'] ?? 1);
                $tabsItems = [];
                foreach ($container['tabs'] ?? [] as $tab) {
                    $tabFields = self::buildFieldComponents(
                        $tab['fields'] ?? [],
                        $fieldMap,
                        $isDetail,
                        $reactiveFields,
                        $module,
                        $clearOnUpdateMap
                    );
                    if (empty($tabFields)) {
                        continue;
                    }
                    // Wrap fields in a grid when columns > 1 (avoids touching JsonFormBuilder)
                    $schema = $tabColumns > 1
                        ? [['component' => 'grid', 'columns' => $tabColumns, 'schema' => $tabFields]]
                        : $tabFields;
                    $tabsItems[] = ['label' => $tab['label'] ?? 'Tab', 'schema' => $schema];
                }
                if (empty($tabsItems)) {
                    continue;
                }
                $entry = ['component' => 'tabs', 'columnSpan' => 'full', 'tabs' => $tabsItems];
                if (!empty($container['title'])) {
                    $entry['label'] = $container['title'];
                }
                $components[] = $entry;

            } elseif ($type === 'grid') {
                $fields = self::buildFieldComponents(
                    $container['fields'] ?? [],
                    $fieldMap,
                    $isDetail,
                    $reactiveFields,
                    $module,
                     $clearOnUpdateMap
                );
                if (empty($fields)) {
                    continue;
                }
                $components[] = [
                    'component' => 'grid',
                    'columns' => (int) ($container['columns'] ?? 2),
                    'columnSpan' => 'full',
                    'schema' => $fields,
                ];

            } else {
                // section (default — backward-compatible with layout_json that has no 'type' key)
                $fields = self::buildFieldComponents(
                    $container['fields'] ?? [],
                    $fieldMap,
                    $isDetail,
                    $reactiveFields,
                    $module,
                    $clearOnUpdateMap
                );
                if (empty($fields)) {
                    continue;
                }
                $components[] = [
                    'component' => 'section',
                    'label' => $container['title'] ?? 'General',
                    'columns' => (int) ($container['columns'] ?? 2),
                    'collapsible' => false,
                    'columnSpan' => 'full',
                    'schema' => $fields,
                ];
            }
        }

        return [
            'title' => $title,
            'model' => "App\\Models\\{$model}",
            'components' => $components,
        ];
    }

    /**
     * Build the field component config array for a list of field names.
     * Shared by section, grid, and tabs containers.
     *
     * @param  string[]  $fieldNames
     * @param  Collection<string, ModuleField>  $fieldMap
     * @param  string[]  $reactiveFields
     * @return array[]
     */
    private static function buildFieldComponents(
        array $fieldNames,
        Collection $fieldMap,
        bool $isDetail,
        array $reactiveFields,
        Module $module,
        array $clearOnUpdateMap = []
    ): array {
        $out = [];

        foreach ($fieldNames as $fieldName) {
            $field = $fieldMap->get($fieldName);
            if ($field === null) {
                continue;
            }

            $componentType = $isDetail
                ? FieldTypeMap::toDetailComponent($field->type)
                : FieldTypeMap::toFormComponent($field->type);

            $component = [
                'component' => $componentType,
                'name' => $field->field_name,
                'label' => $field->label,
            ];

            if (in_array($field->field_name, $reactiveFields, true)) {
                $component['reactive'] = true;
            }
            if (!empty($clearOnUpdateMap[$field->field_name])) {
                $component['clear_on_update'] = array_values(array_unique($clearOnUpdateMap[$field->field_name]));
            }

            if (!$isDetail && $field->required) {
                $component['required'] = true;
            }

            if ($field->always_save_value) {
                // [L23] Key MUST stay 'dehydrate' — JsonFormBuilder reads it at applyCommonFieldOptions.
                $component['dehydrate'] = true;
            }

            if (!$isDetail && in_array($field->type, ['select', 'dynamic_select', 'dropdown', 'enum', 'radio', 'checkboxList', 'checkbox_list'], true)) {
                $default = null;
                if (!empty($field->options) && is_array($field->options)) {
                    foreach ($field->options as $option) {
                        if (!empty($option['default'])) {
                            $default = $option['key'];
                            break;
                        }
                    }
                }
                // $dropdownName = "{$module->fullname}_{$field->field_name}_dom";
                $component['options_source'] = 'helper';
                $component['helper_class'] = 'App\\Helpers\\Studio\\DropdownHandler';
                $component['helper_method'] = 'get';
                $component['helper_params'] = [$module->fullname, $field->field_name];
                if ($default !== null) {
                    $component['default'] = $default;
                }
                if (!empty($field->dependent_field)) {
                    $component['helper_type'] = 'hybrid';
                    $component['helper_params'] = [$module->fullname, $field->field_name, '@' . $field->dependent_field];
                }

            }

            if (in_array($field->type, ['file', 'image', 'fileupload'], true)) {
                $component['disk'] = 'public';
                if (!empty($field->is_multiple)) {
                    $component['multiple'] = true;
                }
            }

            if (in_array($field->type, ['select', 'dynamic_select', 'dropdown', 'enum'], true) && !empty($field->is_multiple)) {
                $component['multiple'] = true;
            }

            if (in_array($field->type, ['image'], true)) {
                $component['image'] = true;
            }

            if ($field->type === 'email') {
                $component['type'] = 'email';
            }

            if ($field->type === 'relationship') {
                $relateConfig = is_array($field->options) ? ($field->options[0] ?? []) : [];
                $component['options_source'] = 'relate';
                if (!empty($relateConfig['relate_module'])) {
                    $component['relate_module'] = $relateConfig['relate_module'];
                    $component['display_field'] = $relateConfig['display_field'] ?? 'name';
                }
                if (!$isDetail) {
                    $component['searchable'] = true;
                }
            }

            if ($field->type === 'url') {
                $component['type'] = 'url';
            }

            if (in_array($field->type, ['money', 'currency'], true)) {
                $component['money'] = true;
            } elseif (in_array($field->type, ['decimal', 'float', 'integer', 'number', 'int', 'biginteger', 'bigint'], true)) {
                $component['numeric'] = true;
            }

            if (!$isDetail) {
                self::applyFieldValidations($component, $field);
            }

            if (!empty($field->visibility_mode) && $field->visibility_mode !== 'always_visible') {
                $key = $field->visibility_mode;
                $conditions = $field->visibility_conditions ?? [];
                if (is_array($conditions)) {
                    foreach ($conditions as &$cond) {
                        if (isset($cond['operator']) && in_array($cond['operator'], ['in', 'not_in'], true)) {
                            $raw = $cond['value'] ?? '';
                            if (is_string($raw)) {
                                $cond['value'] = array_values(array_filter(array_map('trim', explode(',', $raw))));
                            } elseif (is_array($raw)) {
                                $cond['value'] = array_values(array_filter(array_map(fn($v) => is_string($v) ? trim($v) : $v, $raw)));
                            }
                        }
                    }
                    unset($cond);
                }
                if (!empty($field->condition_logic) && $field->condition_logic !== 'and') {
                    $component[$key] = ['logic' => $field->condition_logic, 'conditions' => $conditions];
                } else {
                    $component[$key] = $conditions;
                }
            }

            if ($isDetail) {
                if ($field->field_name === 'created_by') {
                    $component['name'] = 'createdBy.name';
                } elseif ($field->field_name === 'updated_by') {
                    $component['name'] = 'updatedBy.name';
                } elseif (in_array($field->field_name, ['created_at', 'updated_at'], true)) {
                    $component['dateTime'] = 'd M Y H:i';
                }
            }

            $out[] = $component;
        }

        return $out;
    }

    /**
     * Normalize layout_json to the canonical containers array.
     *
     * Supported input formats:
     *  - New typed:  [{"type":"section","title":"...","columns":2,"fields":[...]}, ...]
     *  - New grid:   [{"type":"grid","columns":3,"fields":[...]}, ...]
     *  - New tabs:   [{"type":"tabs","tabs":[{"label":"...","fields":[...]}, ...]}, ...]
     *  - Legacy (no "type" key): [{"title":"...","columns":2,"fields":[...]}] → treated as section
     *  - Legacy flat: ["field1","field2"] → single section
     *  - Empty / null → one empty default section
     *
     * @param  array<mixed>  $raw
     * @return array<int, array{type: string, ...}>
     */
    private static function normalizeContainers(array $raw): array
    {
        if (empty($raw)) {
            return [['type' => 'section', 'title' => 'General', 'columns' => 2, 'fields' => []]];
        }

        // Legacy flat array of strings
        if (isset($raw[0]) && is_string($raw[0])) {
            return [['type' => 'section', 'title' => 'General', 'columns' => 2, 'fields' => array_values(array_filter($raw, 'is_string'))]];
        }

        if (isset($raw[0]) && is_array($raw[0])) {
            return array_map(function ($item) {
                $type = $item['type'] ?? 'section';

                if ($type === 'tabs') {
                    $tabs = array_map(fn($t) => [
                        'label' => $t['label'] ?? 'Tab',
                        'fields' => array_values(array_filter($t['fields'] ?? [], 'is_string')),
                    ], $item['tabs'] ?? []);
                    return [
                        'type' => 'tabs',
                        'title' => $item['title'] ?? null,
                        'columns' => (isset($item['columns']) && is_numeric($item['columns'])) ? (int) $item['columns'] : 1,
                        'tabs' => $tabs,
                    ];
                }

                if ($type === 'grid') {
                    $all = (array) ($item['fields'] ?? []);
                    $fields = array_values(array_filter($all, 'is_string'));
                    return [
                        'type' => 'grid',
                        'columns' => (isset($item['columns']) && is_numeric($item['columns'])) ? (int) $item['columns'] : 2,
                        'fields' => $fields,
                    ];
                }

                // section (default — also handles legacy items with no 'type' key)
                $all = (array) ($item['fields'] ?? []);
                $fields = array_filter($all, 'is_string');
                if (count($fields) < count($all)) {
                    \Illuminate\Support\Facades\Log::warning(
                        'LayoutGenerator: dropped non-string field entries',
                        ['container' => $item['title'] ?? '?']
                    );
                }
                return [
                    'type' => 'section',
                    'title' => $item['title'] ?? 'Section',
                    'columns' => (isset($item['columns']) && is_numeric($item['columns'])) ? (int) $item['columns'] : 2,
                    'fields' => array_values($fields),
                ];
            }, $raw);
        }

        return [['type' => 'section', 'title' => 'General', 'columns' => 2, 'fields' => []]];
    }

    /** @param Collection<string, ModuleField> $fieldMap */
    private static function buildListJson(
        string $model,
        string $resource,
        string $fullname,
        array $fieldNames,
        Collection $fieldMap,
        array $filtersConfig = [],
    ): array {
        $columns = [];

        foreach ($fieldNames as $fieldName) {
            $field = $fieldMap->get($fieldName);
            if ($field === null) {
                continue;
            }

            $column = [
                'type' => FieldTypeMap::toColumnComponent($field->type),
                'name' => $field->field_name,
                'label' => $field->label,
            ];

            if (FieldTypeMap::isBooleanType($field->type)) {
                $column['boolean'] = true;
            }

            if (in_array($field->type, ['image', 'file', 'fileupload'], true)) {
                $column['disk'] = 'public';
            }

            if ($field->searchable) {
                $column['searchable'] = true;
            }

            if ($field->sortable) {
                $column['sortable'] = true;
            }

            // Relationship fields: emit relate config so the table builder can resolve IDs to labels.
            if ($field->type === 'relationship') {
                $relateConfig = is_array($field->options) ? ($field->options[0] ?? []) : [];
                if (!empty($relateConfig['relate_module'])) {
                    $column['options_source'] = 'relate';
                    $column['relate_module'] = $relateConfig['relate_module'];
                }
            }

            // System fields: resolve user IDs to names and format timestamps.
            if ($field->field_name === 'created_by') {
                $column['name'] = 'createdBy.name';
            } elseif ($field->field_name === 'updated_by') {
                $column['name'] = 'updatedBy.name';
            } elseif (in_array($field->field_name, ['created_at', 'updated_at'], true)) {
                $column['date'] = true;
                $column['format'] = 'd M Y H:i';
            }

            $columns[] = $column;
        }

        $filters = [];
        $moduleName = $fullname;
        foreach ($filtersConfig as $fc) {
            $fieldName = $fc['field_name'] ?? null;
            if (!$fieldName) {
                continue;
            }
            $field = $fieldMap->get($fieldName);
            $filterType = match (true) {
                $field !== null && FieldTypeMap::isBooleanType($field->type) => 'boolean',
                $field !== null && in_array($field->type, ['select', 'dynamic_select', 'radio', 'dropdown', 'enum'], true) => 'select',
                default => 'text',
            };
            $filterLabel = !empty($fc['label']) ? $fc['label'] : ($field?->label ?? Str::headline($fieldName));

            $entry = ['type' => $filterType, 'name' => $fieldName, 'label' => $filterLabel];

            if ($filterType === 'select' && $field !== null) {
                $entry['options_source'] = 'helper';
                $entry['helper_class'] = 'App\\Helpers\\Studio\\DropdownHandler';
                $entry['helper_method'] = 'get';
                $entry['helper_params'] = ["{$moduleName}_{$fieldName}_dom"];
            }


            $filters[] = $entry;
        }

        return [
            'title' => $resource,
            'model' => "App\\Models\\{$model}",
            'columns' => $columns,
            'filters' => $filters,
            'actions' => [
                ['type' => 'edit', 'label' => 'Edit', 'ui' => ['icon' => 'heroicon-m-pencil-square', 'hiddenLabel' => true, 'iconButton' => true, 'tooltip' => 'Edit']],
                ['type' => 'view', 'label' => 'Details', 'ui' => ['icon' => 'heroicon-o-eye', 'hiddenLabel' => true, 'iconButton' => true, 'tooltip' => 'Details']],
                [
                    "type" => "group",
                    "label" => "More",
                    "icon" => "heroicon-o-ellipsis-horizontal",
                    "items" => [
                        ['type' => 'delete', 'label' => 'Delete']
                    ]
                ],
            ],
            "record_actions_position" => "BeforeColumns"
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inject type-specific validation rules into a form component config array.
     * Rules are written to `field_rules` / `field_messages` (picked up by
     * JsonFormBuilder::applyCommonFieldOptions) or `validate_json` (textarea only).
     */
    private static function applyFieldValidations(array &$component, ModuleField $field): void
    {
        $type = strtolower($field->type);

        // ── Pincode: exactly 6 digits ─────────────────────────────────────────
        if ($field->field_name === 'pincode' || str_ends_with($field->field_name, '_pincode')) {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'regex:/^\d{6}$/'];
            $component['strict_messages'] = true;
            $component['messages'] = ['regex' => 'Pincode must be exactly 6 digits.'];
            return;
        }

        // ── JSON / array / repeater: valid JSON string ────────────────────────
        if (in_array($type, ['json', 'array', 'repeater'], true)) {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'json'];
            $component['strict_messages'] = true;
            $component['messages'] = ['json' => 'This field must contain valid JSON.'];
            return;
        }

        // ── Phone: digits only, 7–15 characters ──────────────────────────────
        if ($type === 'phone') {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'regex:/^[0-9]+$/', 'min:7', 'max:15'];
            $component['strict_messages'] = true;
            $component['messages'] = [
                'regex' => 'Phone number must contain digits only.',
                'min' => 'Phone number must be at least 7 digits.',
                'max' => 'Phone number must not exceed 15 digits.',
            ];
            return;
        }

        // ── Currency / decimal: format + DB range decimal(15,4) ──────────────
        // DB allows max 15 total digits with 4 decimal places → integer part max 11 digits.
        if (in_array($type, ['currency', 'money', 'decimal', 'float'], true)) {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'regex:/^\d{1,11}(\.\d{1,4})?$/'];
            $component['strict_messages'] = true;
            $component['messages'] = [
                'regex' => 'Enter a valid amount (max 11 integer digits, up to 4 decimal places).',
            ];
            return;
        }

        // ── Email ────────────────────────────────────────────────────────────
        if ($type === 'email') {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'email'];
            $component['strict_messages'] = true;
            $component['messages'] = ['email' => 'Please enter a valid email address.'];
            return;
        }

        // ── URL ───────────────────────────────────────────────────────────────
        if ($type === 'url') {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'url'];
            $component['strict_messages'] = true;
            $component['messages'] = ['url' => 'Please enter a valid URL (e.g. https://example.com).'];
            return;
        }

        // ── Integer / Number ──────────────────────────────────────────────────
        if (in_array($type, ['integer', 'number', 'int', 'biginteger', 'bigint'], true)) {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'integer'];
            $component['strict_messages'] = true;
            $component['messages'] = ['integer' => 'This field must be a whole number.'];
            return;
        }

        // ── Date ──────────────────────────────────────────────────────────────
        if ($type === 'date') {
            $component['validation'] = ['nullable', 'date'];
            $component['strict_messages'] = true;
            $component['messages'] = ['date' => 'Please enter a valid date.'];
            return;
        }

        // ── Datetime / Timestamp ──────────────────────────────────────────────
        if (in_array($type, ['datetime', 'timestamp'], true)) {
            $component['validation'] = ['nullable', 'date'];
            $component['strict_messages'] = true;
            $component['messages'] = ['date' => 'Please enter a valid date and time.'];
            return;
        }

        // ── Time ──────────────────────────────────────────────────────────────
        if ($type === 'time') {
            $component['validation'] = ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
            $component['strict_messages'] = true;
            $component['messages'] = ['regex' => 'Please enter a valid time (HH:MM or HH:MM:SS).'];
            return;
        }

        // ── Color: hex value ──────────────────────────────────────────────────
        if ($type === 'color') {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'];
            $component['strict_messages'] = true;
            $component['messages'] = ['regex' => 'Please enter a valid hex color (e.g. #FF5733).'];
            return;
        }

        // ── Password: minimum length ──────────────────────────────────────────
        if ($type === 'password') {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'string', 'min:8'];
            $component['strict_messages'] = true;
            $component['messages'] = ['min' => 'Password must be at least 8 characters.'];
            return;
        }

        // ── Tags / Checkbox list: must be an array ────────────────────────────
        if (in_array($type, ['tags', 'checkbox_list', 'checkboxlist'], true)) {
            $component['validation'] = ['nullable', 'array'];
            return;
        }

        // ── Textarea / Longtext / Richtext: string content ────────────────────
        if (in_array($type, ['textarea', 'longtext', 'richtext'], true)) {
            $component['validation'] = ['nullable', 'string'];
            return;
        }

        // ── Text / String: enforce column max-length when explicitly set ──────
        if (in_array($type, ['text', 'string'], true) && $field->length > 0) {
            $component['validate_on_blur'] = true;
            $component['validation'] = ['nullable', 'string', 'max:' . (int) $field->length];
            $component['strict_messages'] = true;
            $component['messages'] = ['max' => "This field cannot exceed {$field->length} characters."];
            return;
        }

        // ── Image: restrict to safe image MIME types, block everything else ──
        if ($type === 'image') {
            $component['accepted_file_types'] = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'image/bmp',
            ];
            // 'nullable' omitted — FileUpload required flag handles optionality server-side.
            $component['validation'] = ['mimes:jpg,jpeg,png,gif,webp,svg,bmp'];
            return;
        }

        // ── File / FileUpload: safe document + image types, block executables ─
        if (in_array($type, ['file', 'fileupload'], true)) {
            $component['accepted_file_types'] = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
                'application/zip',
                'application/x-zip-compressed',
                'application/json',
            ];
            $component['validation'] = [
                'mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,json',
            ];
            return;
        }
    }

    /** Returns true when the JSON file already has a non-empty components/columns array. */
    private static function fileHasContent(string $path): bool
    {
        $raw = File::get($path);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return false;
        }

        $items = $data['components'] ?? $data['columns'] ?? [];

        return !empty($items);
    }
}
