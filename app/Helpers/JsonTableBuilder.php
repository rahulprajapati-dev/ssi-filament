<?php

namespace App\Helpers;

use Closure;
use Exception;
use Filament\Actions\Action as ActionClass; // for header custom actions (if you need)
use Filament\Actions\Action as PageAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction as TableBulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use App\Helpers\Studio\DropdownHandler;

class JsonTableBuilder
{
    public static function build(Table $table, array $config): Table
    {
        // columns
        $columns = collect($config['columns'] ?? [])
            ->map(fn ($c) => self::buildColumn($c))
            ->filter()
            ->values()
            ->all();

        if (! empty($columns)) {
            $table = $table->columns($columns);
        }

        // filters
        $filters = collect($config['filters'] ?? [])
            ->map(fn ($f) => self::buildFilter($f))
            ->filter()
            ->values()
            ->all();

        if (! empty($filters)) {
            $table = $table->filters($filters)
                ->filtersFormMaxHeight('200px');
        }
        // ==================== FILTERS LAYOUT LOGIC ====================
        if (!empty($config['filters_layout'])) {
            $layoutStr = strtolower($config['filters_layout']);

            // Map JSON string to Filament 4 Enum
            $layout = match ($layoutStr) {
                'above_content' => FiltersLayout::AboveContent,
                'above_content_collapsible' => FiltersLayout::AboveContentCollapsible,
                'below_content' => FiltersLayout::BelowContent,
                'dropdown' => FiltersLayout::Dropdown,
                'modal' => FiltersLayout::Modal,
                default => FiltersLayout::Dropdown,
            };

            $table->filtersLayout($layout);
        }
        // ======================================================================
        // header actions
        $headerActions = collect($config['headerActions'] ?? [])
            ->map(fn ($h) => self::buildHeaderAction($h))
            ->filter()
            ->values()
            ->all();

        if (! empty($headerActions)) {
            $table = $table->headerActions($headerActions);
        }

        // row actions
        $actions = collect($config['actions'] ?? [])
            ->map(fn ($a) => self::buildRowAction($a))
            ->filter()
            ->values()
            ->all();

        if (! empty($actions)) {
            $table = $table->recordActions($actions);
        }

        // bulk actions
        $bulk = collect($config['bulkActions'] ?? [])
            ->map(fn ($b) => self::buildBulkAction($b))
            ->filter()
            ->values()
            ->all();

        if (! empty($bulk)) {
            $table = $table->toolbarActions($bulk);
        }

        // default sort
        if (! empty($config['defaultSort']['column'])) {
            $dir = $config['defaultSort']['direction'] ?? 'desc';
            $table = $table->defaultSort($config['defaultSort']['column'], $dir);
        }
        // record actions position (Filament 4 enum)
        if (! empty($config['record_actions_position'])) {
            $pos = strtolower($config['record_actions_position']);

            $map = [
                'beforecells' => RecordActionsPosition::BeforeCells,
                'aftercells' => RecordActionsPosition::AfterCells,
                'beforecolumns' => RecordActionsPosition::BeforeColumns,
                'aftercolumns' => RecordActionsPosition::AfterColumns,
                'aftercontent' => RecordActionsPosition::AfterContent,
            ];

            if (isset($map[$pos])) {
                $table->recordActionsPosition($map[$pos]);
            } else {
                Log::warning("Invalid recordActionsPosition: {$config['record_actions_position']}");
            }
        }
        // record_action possible values are null, boolean, or string (method name)
        if (array_key_exists('record_action', $config)) {
            $table->recordAction($config['record_action']);
        }
        if (array_key_exists('record_url', $config)) {
            $table->recordUrl($config['record_url']);
        }
        if (!empty($config['perPage'])) {
            try {
                $table = $table->defaultPaginationPageOption((int) $config['perPage'] ?? 10);
            } catch (\Exception $e) {
                Log::debug('JsonTableBuilder: defaultPaginationPageOption not available', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $table;
    }

    protected static function buildColumn(array $c)
    {
        $type = $c['type'] ?? 'text';
        $name = $c['name'] ?? null;
        $label = $c['label'] ?? null;

        if (! $name) {
            return null;
        }

        // Filament supports dotted relation paths, so use $name as-is.
        return match ($type) {
            'text' => tap(TextColumn::make($name)->label($label ?? Str::headline($name)), function ($col) use ($c, $name) {
                // determine searchable flags (support both simple and nested forms)
                $isSearchable = false;
                $isIndividual = false;
                if (!empty($c['visible_tabs'])) {
                    $tabs = is_array($c['visible_tabs']) ? $c['visible_tabs'] : [$c['visible_tabs']];
                    $col->visible(fn ($livewire) => in_array($livewire->activeTab, $tabs));
                }

                if (!empty($c['hidden_tabs'])) {
                    $tabs = is_array($c['hidden_tabs']) ? $c['hidden_tabs'] : [$c['hidden_tabs']];
                    $col->hidden(fn ($livewire) => in_array($livewire->activeTab, $tabs));
                }

                if (isset($c['searchable'])) {
                    if (is_array($c['searchable'])) {
                        // { "searchable": { "global": true, "individual": true } }
                        $isSearchable = (bool) ($c['searchable']['global'] ?? true);
                        $isIndividual = (bool) ($c['searchable']['individual'] ?? false);
                    } else {
                        // "searchable": true
                        $isSearchable = (bool) $c['searchable'];
                        // allow separate flag override
                        $isIndividual = (bool) ($c['individual_search'] ?? $c['searchable_individual'] ?? false);
                    }
                } else {
                    // older flag style: "individual_search": true
                    $isIndividual = (bool) ($c['individual_search'] ?? $c['searchable_individual'] ?? false);
                    $isSearchable = $isIndividual ? true : false;
                }

                // apply other options
                if (! empty($c['sortable'])) {
                    $col->sortable();
                }
                if (isset($c['rupee']) && $c['rupee']) {
                    $col->prefix('₹')
                    ->numeric();
                }
                if (! empty($c['wrap'])) {
                    $col->wrap();
                }
                if (! empty($c['limit'])) {
                    $col->limit((int)$c['limit']);
                }
                if (! empty($c['date'])) {
                    $format     = $c['format'] ?? 'd M Y';
                    $defaultVal = $c['default'] ?? null;
                    // Use formatStateUsing so null/empty states return the default
                    // instead of passing it through Carbon (which would throw).
                    $col->formatStateUsing(function ($state) use ($format, $defaultVal) {
                        if ($state === null || $state === '' || $state === false) {
                            return $defaultVal;
                        }
                        try {
                            return \Carbon\Carbon::parse($state)->format($format);
                        } catch (\Throwable $e) {
                            return $defaultVal;
                        }
                    });
                }
                $enum = $c['enum'] ?? null;
                if (!empty($enum)) {
                    $col->formatStateUsing(fn ($state) => $enum[$state] ?? $state);
                }
                // Only apply ->default() for non-date columns; date columns embed the
                // default inside their formatStateUsing to prevent Carbon from parsing it.
                if (! empty($c['default']) && empty($c['date'])) {
                    $col->default($c['default']);
                }

                // Handle dropdown value mapping
                if (!empty($c['dropdown'])) {
                    $dropdownType = $c['dropdown'];
                    $col->formatStateUsing(function ($state) use ($dropdownType) {
                        $options = DropdownHandler::get($dropdownType);

                        return $options[$state] ?? $state;
                    });
                }

                // format_hook: "ClassName@method" — receives ($state, $record)
                if (! empty($c['format_hook'])) {
                    $hookString = $c['format_hook'];
                    $col->formatStateUsing(function ($state, $record) use ($hookString) {
                        if (str_contains($hookString, '@')) {
                            [$class, $method] = explode('@', $hookString);

                            return $class::$method($state, $record);
                        }

                        return $state;
                    });
                }

                // apply searchable with individual option where supported
                if ($isSearchable) {
                    try {
                        if (!empty($c['dropdown'])) {
                            $dropdownType = $c['dropdown'];
                            $col->searchable(isIndividual: (bool) $isIndividual, query: function ($query, $search) use ($name, $dropdownType) {
                                $options = DropdownHandler::get($dropdownType);
                                if (!is_array($options)) {
                                    return $query->where($name, 'like', "%{$search}%");
                                }
                                $matchingKeys = array_keys(array_filter($options, function ($val) use ($search) {
                                    return str_contains(strtolower($val), strtolower($search));
                                }));

                                if (!empty($matchingKeys)) {
                                    return $query->whereIn($name, $matchingKeys);
                                }

                                return $query->where($name, 'like', "%{$search}%");
                            });
                        } else {
                            // Filament's TextColumn::searchable($isIndividual = false)
                            if (method_exists($col, 'searchable')) {
                                $col->searchable(isIndividual: (bool) $isIndividual);
                            } else {
                                // fallback: if searchable() missing, try ->searchable() without param
                                if (method_exists($col, 'searchable')) {
                                    $col->searchable();
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // safe fallback: don't break the page, log for debugging
                        Log::debug('JsonTableBuilder: searchable failed for column ' . $name . ' - ' . $e->getMessage());
                    }
                }
            }),

            'badge' => tap(TextColumn::make($name)->label($label ?? Str::headline($name))->badge(), function ($col) use ($c, $name) {
                if (! empty($c['sortable'])) {
                    $col->sortable();
                }
                $isSearchable = false;
                $isIndividual = false;

                if (!empty($c['visible_tabs'])) {
                    $tabs = is_array($c['visible_tabs']) ? $c['visible_tabs'] : [$c['visible_tabs']];
                    $col->visible(fn ($livewire) => in_array($livewire->activeTab, $tabs));
                }

                if (!empty($c['hidden_tabs'])) {
                    $tabs = is_array($c['hidden_tabs']) ? $c['hidden_tabs'] : [$c['hidden_tabs']];
                    $col->hidden(fn ($livewire) => in_array($livewire->activeTab, $tabs));
                }
                if (isset($c['searchable'])) {
                    if (is_array($c['searchable'])) {
                        // { "searchable": { "global": true, "individual": true } }
                        $isSearchable = (bool) ($c['searchable']['global'] ?? true);
                        $isIndividual = (bool) ($c['searchable']['individual'] ?? false);
                    } else {
                        // "searchable": true
                        $isSearchable = (bool) $c['searchable'];
                        // allow separate flag override
                        $isIndividual = (bool) ($c['individual_search'] ?? $c['searchable_individual'] ?? false);
                    }
                } else {
                    // older flag style: "individual_search": true
                    $isIndividual = (bool) ($c['individual_search'] ?? $c['searchable_individual'] ?? false);
                    $isSearchable = $isIndividual ? true : false;
                }
                // apply searchable with individual option where supported
                if ($isSearchable) {
                    try {
                        if (!empty($c['dropdown'])) {
                            $dropdownType = $c['dropdown'];
                            $col->searchable(isIndividual: (bool) $isIndividual, query: function ($query, $search) use ($name, $dropdownType) {
                                $options = getDropdownValue($dropdownType);
                                if (!is_array($options)) {
                                    return $query->where($name, 'like', "%{$search}%");
                                }
                                $matchingKeys = array_keys(array_filter($options, function ($val) use ($search) {
                                    return str_contains(strtolower($val), strtolower($search));
                                }));

                                if (!empty($matchingKeys)) {
                                    return $query->whereIn($name, $matchingKeys);
                                }

                                return $query->where($name, 'like', "%{$search}%");
                            });
                        } else {
                            // Filament's TextColumn::searchable($isIndividual = false)
                            if (method_exists($col, 'searchable')) {
                                $col->searchable(isIndividual: (bool) $isIndividual);
                            } else {
                                // fallback: if searchable() missing, try ->searchable() without param
                                if (method_exists($col, 'searchable')) {
                                    $col->searchable();
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // safe fallback: don't break the page, log for debugging
                        Log::debug('JsonTableBuilder: searchable failed for column ' . $name . ' - ' . $e->getMessage());
                    }
                }
                if (! empty($c['colors']) && is_array($c['colors'])) {
                    // Expect colors like { "success": "active", "danger": "inactive" }
                    $colorMap = $c['colors'];
                    if (method_exists($col, 'color')) {
                        $col->color(fn ($state) => $colorMap[$state] ?? 'gray');
                    }
                }

                // color_map: { "create": "success", "edit": "warning", "detail": "info", "list": "primary" }
                if (! empty($c['color_map']) && is_array($c['color_map'])) {
                    $colorMap = $c['color_map'];
                    $col->color(fn ($state) => $colorMap[$state] ?? 'gray');
                }

                $enum = $c['enum'] ?? null;
                if ($enum) {
                    $col->formatStateUsing(fn ($state) => $enum[$state] ?? $state);
                }

                // Handle dropdown value mapping for badges
                if (! empty($c['dropdown'])) {
                    $dropdownType = $c['dropdown'];
                    $col->formatStateUsing(function ($state) use ($dropdownType) {
                        $options = DropdownHandler::get($dropdownType);

                        return $options[$state] ?? $state;
                    });
                }
            }),

            'icon' => tap(IconColumn::make($name)->label($label ?? Str::headline($name)), function ($col) use ($c) {
                // Tab visibility logic
                if (!empty($c['visible_tabs'])) {
                    $tabs = is_array($c['visible_tabs']) ? $c['visible_tabs'] : [$c['visible_tabs']];
                    $col->visible(fn ($livewire) => in_array($livewire->activeTab, $tabs));
                }

                if (!empty($c['hidden_tabs'])) {
                    $tabs = is_array($c['hidden_tabs']) ? $c['hidden_tabs'] : [$c['hidden_tabs']];
                    $col->hidden(fn ($livewire) => in_array($livewire->activeTab, $tabs));
                }

                // Standard icon options
                if (!empty($c['boolean'])) {
                    $col->boolean();
                }

                // Support for dynamic icons based on state
                if (!empty($c['options']) && is_array($c['options'])) {
                    $col->icon(fn ($state) => $c['options'][$state] ?? $c['default_icon'] ?? null);
                }
            }),
            default => TextColumn::make($name)->label($label ?? Str::headline($name)),
        };
    }

    protected static function applyActionOptions($action, array $cfg): void
    {
        // icon
        if (! empty($cfg['icon']) && method_exists($action, 'icon')) {
            $action->icon($cfg['icon']);
        }
        // label again (if needed)
        if (! empty($cfg['label']) && method_exists($action, 'label')) {
            $action->label($cfg['label']);
        }

        // slideOver / modal
        if (! empty($cfg['presentation'])) {
            // presentation could be "slideOver" or "modal"
            $present = $cfg['presentation'];
            if ($present === 'slideOver' && method_exists($action, 'slideOver')) {
                $action->slideOver();
            } elseif ($present === 'modal' && method_exists($action, 'modal')) {
                $action->modal();
            }
        }

        // boolean flags alternative
        if (! empty($cfg['slideOver']) && method_exists($action, 'slideOver')) {
            $action->slideOver();
        }
        if (! empty($cfg['modal']) && method_exists($action, 'modal')) {
            $action->modal();
        }

        // modalActionHidden
        if (! empty($cfg['submitActionHidden'])) {
            $action->modalSubmitAction(false);
        }

        // modalWidth mapping
        if (! empty($cfg['modalWidth']) && method_exists($action, 'modalWidth')) {
            $mw = $cfg['modalWidth']; // user can pass e.g. "FiveExtraLarge" or integer
            if (defined(\Filament\Support\Enums\Width::class . '::' . $mw)) {
                $widthEnum = constant(\Filament\Support\Enums\Width::class . '::' . $mw);
                $action->modalWidth($widthEnum);
            }
        }

        // modalHeading
        if (! empty($cfg['modalHeading']) && method_exists($action, 'modalHeading')) {
            $action->modalHeading($cfg['modalHeading']);
        }

        // other presentation flags: modalSize / modalHeight etc (apply if methods exist)
        if (! empty($cfg['modalHeight']) && method_exists($action, 'modalHeight')) {
            $action->modalHeight($cfg['modalHeight']);
        }
        // modalDescription
        if (!empty($cfg['modalDescription']) && method_exists($action, 'modalDescription')) {
            $action->modalDescription($cfg['modalDescription']);
        }

        // modalSubmitActionLabel
        if (!empty($cfg['modalSubmitActionLabel']) && method_exists($action, 'modalSubmitActionLabel')) {
            $action->modalSubmitActionLabel($cfg['modalSubmitActionLabel']);
        }
    }

    protected static function buildFilter(array $f)
    {
        $type = $f['type'] ?? 'select';
        $name = $f['name'] ?? null;
        $label = $f['label'] ?? Str::headline($name);

        if (! $name) {
            return null;
        }

        if ($type === 'select') {
            $source = $f['options_source'] ?? 'static';

            if ($source === 'static') {
                $options = $f['options'] ?? [];
                if (str_contains($name, '.')) {
                    [$relation, $column] = explode('.', $name, 2);

                    return SelectFilter::make($name)
                        ->label($label)
                        ->options($options)
                        ->query(function ($query, array $data) use ($relation, $column) {
                            $value = $data['value'] ?? null;

                            if ($value === null || $value === '') {
                                return $query;
                            }

                            return $query->whereHas($relation, function ($q) use ($column, $value) {
                                $q->where($column, $value);
                            });
                        });
                }

                return SelectFilter::make($name)->label($label)->options($options);
            }

            if ($source === 'config') {
                $cfg = $f['config_key'] ?? null;
                $opts = $cfg ? config($cfg, []) : [];

                return SelectFilter::make($name)->label($label)->options($opts);
            }

            if ($source === 'helper') {
                $helperClass = $f['helper_class'] ?? null;
                $helperMethod = $f['helper_method'] ?? null;
                $helperParams = $f['helper_params'] ?? [];

                if ($helperClass && $helperMethod && class_exists($helperClass) && method_exists($helperClass, $helperMethod)) {
                    return SelectFilter::make($name)
                        ->label($label)
                        ->options(fn () => $helperClass::$helperMethod(...$helperParams));
                }
            }

            if ($source === 'relationship') {
                $relationship = $f['relationship'] ?? null;
                $title = $f['title_column'] ?? 'name';
                $role = auth()->user()?->getRoleNames()->first();
                $allowEmpty = $f['allow_empty_for_roles'][$role]
                    ?? $f['allow_empty_for_roles']['default']
                    ?? true;

                return SelectFilter::make($name)
                    ->label($label)
                    ->relationship(
                        $relationship,
                        $title,
                        hasEmptyOption: $allowEmpty,
                        modifyQueryUsing: fn ($query) =>
                        $query->whereNotNull($title)
                    );
            }
        }

        return null;
    }

    /**
     * Build header action (mapped to Filament Actions)
     *
     * @param array $h
     * @return \Filament\Actions\Action|\Filament\Actions\ActionGroup|null
     */
    protected static function buildHeaderAction(array $h)
    {
        $type = $h['type'] ?? 'button';
        $label = $h['label'] ?? null;
        $icon = $h['ui']['icon'] ?? ($h['icon'] ?? null);
        $actionName = $h['action'] ?? null;

        return match ($type) {
            'create' => tap(CreateAction::make()->label($label), function ($act) use ($icon, $h) {
                if ($icon && method_exists($act, 'icon')) {
                    $act->icon($icon);
                }
                // UI customization (slideOver/modal) for header create action
                self::applyUiOptionsToAction($act, $h['ui'] ?? []);
            }),
            'button' => tap(PageAction::make($actionName ?? 'custom')->label($label), function ($act) use ($icon, $h, $actionName) {
                if ($icon && method_exists($act, 'icon')) {
                    $act->icon($icon);
                }
                self::applyUiOptionsToAction($act, $h['ui'] ?? []);
                if ($actionName && method_exists($act, 'action')) {
                    $act->action($actionName);
                }
            }),
            default => tap(PageAction::make($actionName ?? 'action')->label($label), function ($act) use ($icon, $h) {
                if ($icon && method_exists($act, 'icon')) {
                    $act->icon($icon);
                }
                self::applyUiOptionsToAction($act, $h['ui'] ?? []);
            }),
        };
    }

    protected static function normalizeConditions(array $config): array
    {
        // If it looks like a single condition (has 'field'), wrap in an array
        if (isset($config['field'])) {
            return [$config];
        }

        // Already a list of conditions
        return $config;
    }

    protected static function evaluateConditions(Closure $get, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value'] ?? null;

            if (!$field) {
                continue;
            }

            $actual = $get($field);

            // Normalize operator
            $op = strtolower((string) $operator);

            $result = match ($op) {
                '=', '==' => $actual == $expected,
                '!=' => $actual != $expected,
                '>' => $actual > $expected,
                '>=' => $actual >= $expected,
                '<' => $actual < $expected,
                '<=' => $actual <= $expected,
                'in' => is_array($expected) ? in_array($actual, $expected, true) : false,
                'not_in' => is_array($expected) ? !in_array($actual, $expected, true) : false,
                'is_null' => $actual === null || $actual === '',
                'not_null' => !($actual === null || $actual === ''),
                default => true,
            };

            if (!$result) {
                return false; // AND logic: one false breaks
            }
        }

        return true;
    }

    /**
     * Build a row action or action group for the table.
     *
     * Supports:
     *  - { "type":"edit" }
     *  - { "type":"view" }
     *  - { "type":"delete" }
     *  - { "type":"group", "items":[ ... ] }
     *
     * @param array $a
     * @return \Filament\Actions\Action|\Filament\Actions\ActionGroup|null
     */
    public static function buildRowAction(array $a)
    {
        $type = $a['type'] ?? 'view';
        $label = $a['label'] ?? null;
        $name = $a['name'] ?? 'action_' . uniqid();
        $ui = $a['ui'] ?? [];

        // action group
        if (in_array($type, ['group', 'actionGroup', 'action_group'])) {
            $children = collect($a['items'] ?? [])
                ->map(fn ($it) => self::buildRowAction($it))
                ->filter()
                ->values()
                ->all();

            // Filament\Actions\ActionGroup::make(array $actions)
            try {
                return ActionGroup::make($children)
                    ->label($label ?? null)
                    // optionally icon on group (if supported)
                    ->icon($a['icon'] ?? ($ui['icon'] ?? null));
            } catch (\TypeError $e) {
                // Some Filament versions expect different args — attempt fallback by creating group via new signature
                try {
                    $group = new ActionGroup($children);
                    if (method_exists($group, 'label') && $label) {
                        $group->label($label);
                    }
                    if (method_exists($group, 'icon') && ($a['icon'] ?? $ui['icon'] ?? null)) {
                        $group->icon($a['icon'] ?? $ui['icon']);
                    }

                    return $group;
                } catch (\Throwable $e2) {
                    Log::debug('JsonTableBuilder: ActionGroup make fallback failed: ' . $e2->getMessage());

                    // fallback: return first child action so UI doesn't break
                    return $children[0] ?? null;
                }
            }
        }
        if ($type == 'activity_log') {
            $name = $a['name'] ?? 'change_log';
            $a['ui']['icon'] = $a['ui']['icon'] ?? 'heroicon-o-clock';
            $a['ui']['slideOver'] = $a['ui']['slideOver'] ?? true;
            $a['ui']['modalWidth'] = $a['ui']['modalWidth'] ?? 'SevenExtraLarge';
            $ui = $a['ui']; // Refresh $ui reference
        }

        // 1. Create the Action Instance
        $act = match ($type) {
            'edit' => EditAction::make()->label($label),
            'view' => ViewAction::make()->label($label),
            'delete' => DeleteAction::make()->label($label),
            'activity_log' => ActionClass::make($name)->label($label ?? 'Change Log')->modal(),
            'popup' => ActionClass::make($name)->label($label)->modal(),
            'custom' => ActionClass::make($name)->label($label), // Generic Action
            default => ActionClass::make($a['action'] ?? $name)->label($label),
        };
        //requiresConfirmation
        if (!empty($a['requires_confirmation']) && method_exists($act, 'requiresConfirmation')) {
            $act->requiresConfirmation();
            if (!empty($a['modal_icon'])) {
                $act->modalIcon($a['modal_icon']);
            }
            if (!empty($a['modal_description'])) {
                $act->modalDescription($a['modal_description']);
            }
            if (!empty($a['modal_heading'])) {
                $act->modalHeading($a['modal_heading']);
            }
        }

        // 2. Apply UI Options (Icon, Modal, Color)
        self::applyUiOptionsToAction($act, $ui);

        if (!empty($a['url_route'])) {
            // Allows defining a route name in JSON: "url_route": "filament.admin.resources.stocks.delivery"
            $act->url(fn ($record) => route($a['url_route'], ['record' => $record]));
        } elseif (!empty($a['url'])) {
            // Allows raw URL string
            $act->url($a['url']);

            // Optional: Support opening in new tab if requested
            if (!empty($a['open_new_tab'])) {
                $act->openUrlInNewTab();
            }
        }

        // We look for 'form' or 'schema' key in JSON.
        $schemaDef = $a['schema'] ?? $a['form'] ?? null;
        // 3. Handle Form (JSON array OR method name)
        if ($schemaDef) {
            if (is_string($schemaDef)) {
                $act->schema(function (\Livewire\Component $livewire) use ($schemaDef) {
                    // This returns array of components
                    return $livewire->{$schemaDef}();
                });
            } elseif (is_array($schemaDef)) {
                $act->schema(JsonFormBuilder::buildActionSchema($schemaDef));
            }
        }

        // Special handling for activity_log type to inject schema/hook automatically
        if ($type == 'activity_log') {
            // Force hide submit action for logs
            $act->modalSubmitAction(false);

            // Set the premium list-view schema
            $act->schema([
                \Filament\Schemas\Components\View::make('filament.components.activity-log-list'),
            ]);

            // Bind the generic activity log hook
            self::bindHook($act, 'fillForm', 'App\\Helpers\\CommonHelper@changeLogFillForm');

            // Explicitly ensure action callback exists to trigger modal
            $act->action(fn () => null);
        }
        if ($type == 'popup') {
            // Bind the generic activity log hook
            self::bindHook($act, 'fillForm', '\\App\\Filament\\Resources\\Stocks\\Hooks\\MFCHooks@beforeWindowStickerFill');

            // Explicitly ensure action callback exists to trigger modal
            $act->action(function ($record, array $data, $livewire) {
                $freshRecord = $record->fresh();

                $payload = [
                    'stock_id' => $freshRecord->id,
                    'insurance_date' => $freshRecord->insurance_exp_date,
                    'warranty_type' => $freshRecord->warranty_recommended,
                    ...$data,
                ];

                $url = route('window-sticker');
                $csrf = csrf_token();

                // Build fields string
                $fields = collect($payload)
                    ->map(
                        fn ($value, $key) =>
                        "<input type='hidden' name='" . e($key) . "' value='" . e($value) . "'>"
                    )
                    ->implode('');

                $livewire->dispatch('close-modal');

                // Submit via JS in new tab
                $livewire->js("
                    (function() {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{$url}';
                        form.target = '_blank';
                        form.innerHTML = `<input type='hidden' name='_token' value='{$csrf}'>{$fields}`;
                        document.body.appendChild(form);
                        form.submit();
                        document.body.removeChild(form);
                    })();
                ");
            });
        }

        // 4. Handle Lifecycle Hooks
        if (!empty($a['hooks'])) {
            foreach ($a['hooks'] as $hook => $method) {
                if (strpos($method, '@') === false) {
                    /*
                     * The format of the hook method is not correct: \\App\\Filament\\Resources\\Stocks\\Traits@checkStockAvailability
                     * It should be in the format \\Namespace\\ClassName@methodName
                     */
                    throw new Exception('The format of the hook method is not correct: ' . $method . '. It should be in the format \\Namespace\\ClassName@methodName');
                }

                self::bindHook($act, $hook, $method);
            }
        }

        // 5. Handle Visibility - combine roles + visible_when + hidden_when into ONE call
        $visibleRoles = !empty($a['visible_roles'])
            ? (is_array($a['visible_roles']) ? $a['visible_roles'] : [$a['visible_roles']])
            : null;

        // 5. Handle Visibility - combine roles + visible_when + hidden_when into ONE call
        $hiddenRoles = !empty($a['hidden_roles'])
            ? (is_array($a['hidden_roles']) ? $a['hidden_roles'] : [$a['hidden_roles']])
            : null;

        $visibleWhen = $a['visible_when'] ?? null;
        $hiddenWhen = $a['hidden_when'] ?? null;
        $visibleHook = $a['visible_hook'] ?? null;

        $act->visible(function ($record) use ($visibleRoles, $hiddenRoles, $visibleWhen, $hiddenWhen, $visibleHook) {
            // 1. Role check
            if ($visibleRoles) {
                $user = \Illuminate\Support\Facades\Auth::user();
                if (!$user || !$user->hasAnyRole($visibleRoles)) {
                    return false;
                }
            }
            // 1. Role check
            if ($hiddenRoles) {
                $user = \Illuminate\Support\Facades\Auth::user();
                if (!$user || $user->hasAnyRole($hiddenRoles)) {
                    return false;
                }
            }

            // 2. visible_hook check
            if ($visibleHook) {
                [$class, $method] = explode('@', $visibleHook);
                if (!app()->call([$class, $method], ['record' => $record])) {
                    return false;
                }
            }

            // 3. visible_when check
            if ($visibleWhen) {
                // If single condition convert to array
                $conditions = self::normalizeConditions($visibleWhen);
                $get = fn ($field) => $record->{$field} ?? null;
                if (!self::evaluateConditions($get, $conditions)){
                    return false;
                    
                }
            }

            // 4. hidden_when check
            if ($hiddenWhen) {
                $conditions = self::normalizeConditions($hiddenWhen);
                $get = fn ($field) => $record->{$field} ?? null;
                if (self::evaluateConditions($get, $conditions)) {
                    return false;
                }
            }

            return true;
        });

        return $act;
    }

    /**
     * Bind a JSON hook string to a Livewire Component method using Dependency Injection.
     */
    protected static function bindHook($action, string $hookType, string $methodHook)
    {
        $callback = self::resolveHook($methodHook);

        /*
        EditAction::make()
    ->beforeFormFilled(function () {
        // Runs before the form fields are populated from the database.
    })
    ->afterFormFilled(function () {
        // Runs after the form fields are populated from the database.
    })
    ->beforeFormValidated(function () {
        // Runs before the form fields are validated when the form is saved.
    })
    ->afterFormValidated(function () {
        // Runs after the form fields are validated when the form is saved.
    })
    ->before(function () {
        // Runs before the form fields are saved to the database.
    })
    ->after(function () {
        // Runs after the form fields are saved to the database.
    })


        */
        match ($hookType) {
            'action' => $action->action($callback),
            'before_form_filled' => $action->beforeFormFilled($callback),
            'after_form_filled' => $action->afterFormFilled($callback),
            'before_form_validated' => $action->beforeFormValidated($callback),
            'after_form_validated' => $action->afterFormValidated($callback),
            'before' => $action->before($callback),
            'after' => $action->after($callback),
            'fillForm' => $action->fillForm($callback),
            'mount' => $action->mountUsing($callback),
            default => null,
        };
    }

    protected static function resolveHook($methodHook): ?callable
    {
        // We use a closure that requests dependencies via Injection.
        // This ensures Filament passes us the Livewire component, Action, and Record/Data if available.
        return function (
            \Livewire\Component $livewire,
            \Filament\Actions\Action $action,
            $record = null,
            $data = null,
            $form = null // mountUsing provides form
        ) use ($methodHook) {
            // Resolve Resource class from Livewire component
            $resourceClass = null;
            if (method_exists($livewire, 'getResource')) {
                $resourceClass = $livewire->getResource(); // Using instance or static call depending on component
            }
            // Fallback for static getResource on page classes
            if (!$resourceClass && method_exists($livewire, 'getResource')) { // try static
                try {
                    $resourceClass = $livewire::getResource();
                } catch (\Throwable $t) {
                }
            }

            [$class, $method] = explode('@', $methodHook);

            // Determine target to call
            $targetClass = $class;
            $useStatic = (new \ReflectionMethod($class, $method))->isStatic();
            $useResource = false;

            if (trait_exists($class)) {
                if ($resourceClass && method_exists($resourceClass, $method)) {
                    $targetClass = $resourceClass;
                    $useResource = true;
                } else {
                    // Check if trait method is static and callable directly?
                    if (method_exists($class, $method) && (new \ReflectionMethod($class, $method))->isStatic()) {
                        $useStatic = true;
                        $targetClass = $class;
                    } else {
                        // Fallback or error
                        if (!$resourceClass) {
                            // Try to execute on the livewire component itself if acceptable?
                            // But error message should be clear.
                            throw new \RuntimeException("Cannot execute trait hook [{$methodHook}]. Resource context not found.");
                        }
                        throw new \RuntimeException("Method [{$method}] not found on Resource [{$resourceClass}].");
                    }
                }
            }

            // Check if calling static
            if (!$useStatic) {
                // If it's a regular class or resource, check if method is static
                if (method_exists($targetClass, $method)) {
                    $useStatic = (new \ReflectionMethod($targetClass, $method))->isStatic();
                }
            }

            // Normalize arguments to pass to the hook
            // User code expects: (record, data, livewire, action)
            // But sometimes 'data' is null or array.
            $callArgs = [$record, $data ?? [], $livewire, $action];

            if ($useStatic) {
                return $targetClass::{$method}(...$callArgs);
            }

            // Instance call
            // If it is the resource, we can try new instance
            if ($useResource || is_subclass_of($targetClass, \Filament\Resources\Resource::class)) {
                return (new $targetClass())->{$method}(...$callArgs);
            }

            // Otherwise use container
            return app($targetClass)->{$method}(...$callArgs);
        };
    }

    /**
     * Apply UI options from JSON to an Action instance, if the methods exist on the Action
     *
     * Supported UI keys:
     *  - icon (string)
     *  - slideOver (bool)  -> calls ->slideOver()
     *  - modalHeading (string) -> ->modalHeading(...)
     *  - modalWidth (string) -> ->modalWidth(Width::<Constant>) if possible, otherwise pass raw string
     *
     * @param \Filament\Actions\Action $act
     * @param array $ui
     * @return void
     */
    protected static function applyUiOptionsToAction($act, array $ui = []): void
    {
        // icon
        if (! empty($ui['icon']) && method_exists($act, 'icon')) {
            $act->icon($ui['icon']);
        }
        // iconButton — render the action as an icon-only button
        if ((! empty($ui['iconButton']) || ! empty($ui['icon_button'])) && method_exists($act, 'iconButton')) {
            $act->iconButton();
        }

        // hiddenLabel — hide the action's text label
        if ((! empty($ui['hiddenLabel']) || ! empty($ui['hidden_label'])) && method_exists($act, 'hiddenLabel')) {
            $act->hiddenLabel();
        }

        // tooltip — supports plain string
        $tooltip = $ui['tooltip'] ?? null;
        if (! empty($tooltip) && is_string($tooltip) && method_exists($act, 'tooltip')) {
            $act->tooltip($tooltip);
        }

        // slideOver (optional)
        if (! empty($ui['slideOver']) || ! empty($ui['slide_over'])) {
            if (method_exists($act, 'slideOver')) {
                $act->slideOver();
            } else {
                // some Filament versions use ->slideOver(true) or ->slideOver('panel')
                try {
                    if (method_exists($act, 'slideOver')) {
                        $act->slideOver(true);
                    }
                } catch (\Throwable $e) {
                    Log::debug('JsonTableBuilder: slideOver not applied: ' . $e->getMessage());
                }
            }
        }

        // submitActionHidden
        if (! empty($ui['submitActionHidden'])) {
            $act->modalSubmitAction(false);
        }

        // modalHeading
        if (! empty($ui['modalHeading']) && method_exists($act, 'modalHeading')) {
            $act->modalHeading($ui['modalHeading']);
        }

        // modalWidth - try to map friendly names to Width constants if available
        if (! empty($ui['modalWidth']) && method_exists($act, 'modalWidth')) {
            $widthValue = $ui['modalWidth'];
            // try to resolve Filament\Actions\Modal\Width::FiveExtraLarge etc.
            $resolved = self::resolveModalWidth($widthValue);
            try {
                $act->modalWidth($resolved);
            } catch (\Throwable $e) {
                // fallback: try passing raw string (some versions accept strings)
                try {
                    $act->modalWidth($widthValue);
                } catch (\Throwable $e2) {
                    Log::debug('JsonTableBuilder: modalWidth not applied: ' . $e2->getMessage());
                }
            }
        }

        // modalDescription
        if (!empty($ui['modalDescription']) && method_exists($act, 'modalDescription')) {
            $act->modalDescription(new HtmlString($ui['modalDescription']));
        }

        // modalSubmitActionLabel
        if (!empty($ui['modalSubmitActionLabel']) && method_exists($act, 'modalSubmitActionLabel')) {
            $act->modalSubmitActionLabel($ui['modalSubmitActionLabel']);
        }
    }

    /**
     * Resolve modalWidth value (string) to actual constant if possible.
     * Accepts names like "fiveExtraLarge", "FiveExtraLarge", "xl", "lg" etc.
     */
    protected static function resolveModalWidth(string $value)
    {
        // Common Filament enum path: Filament\Actions\Modal\Width
        $candidates = [
            \Filament\Support\Enums\Width::class,
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                // try various casings
                $variants = [
                    $value,
                    Str::of($value)->camel()->ucfirst()->__toString(),
                    Str::studly($value),
                    Str::upper($value),
                ];
                foreach ($variants as $v) {
                    if (defined($class . '::' . $v)) {
                        return constant($class . '::' . $v);
                    }
                }
                // fallback: try common named constants mapping
                $map = [
                    'fiveExtraLarge' => $class . '::FiveExtraLarge',
                    'five_extra_large' => $class . '::FiveExtraLarge',
                    'xl' => $class . '::ExtraLarge',
                    'lg' => $class . '::Large',
                ];
                if (isset($map[$value]) && defined($map[$value])) {
                    return constant($map[$value]);
                }
            }
        }

        // if nothing found, return original value — caller will attempt to use it
        return $value;
    }

    protected static function buildBulkAction(array $b)
    {
        $type = $b['type'] ?? 'delete';
        $label = $b['label'] ?? null;
        $action = $b['action'] ?? null;

        return match ($type) {
            'delete' => DeleteBulkAction::make()->label($label),
            default => TableBulkAction::make($action ?? 'bulk')->label($label),
        };
    }

    protected static function guessRelatedModelClass(?string $relationship)
    {
        if (! $relationship) {
            throw new \RuntimeException('Relationship name missing for guessing model');
        }

        $candidate = 'App\\Models\\' . Str::studly(Str::singular($relationship));
        if (class_exists($candidate)) {
            return $candidate;
        }

        throw new \RuntimeException("Cannot guess related model for relationship {$relationship}");
    }
}
