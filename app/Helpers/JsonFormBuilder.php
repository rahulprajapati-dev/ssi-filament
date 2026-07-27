<?php

namespace App\Helpers;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Form;
use App\Helpers\Studio\DropdownHandler;
use Illuminate\Validation\ValidationException;

class JsonFormBuilder
{
    public static function buildSchema(Schema $schema, array $config): Schema
    {
        $components = $config['components'] ?? [];

        $schema = $schema->schema(self::buildComponents($components));

        return $schema;
    }

    public static function buildActionSchema(array $config): array
    {
        $components = $config['components'] ?? [];

        return self::buildComponents($components);
    }

    public static function buildComponents(array $items): array
    {
        return collect($items)
            ->map(fn (array $item) => self::buildComponent($item))
            ->filter()
            ->values()
            ->all();
    }

    protected static function buildRelationship_old(array $item): Components\Section|Components\Group
    {
        $relation = $item['name'] ?? null;
        $label = $item['label'] ?? null;
        $columns = $item['columns'] ?? null;

        $rawChildren = $item['schema'] ?? [];

        $prefixed = collect($rawChildren)
            ->map(function (array $child) use ($relation) {
                if (! empty($child['name']) && ! str_contains($child['name'], '.')) {
                    $child['name'] = $relation.'.'.$child['name'];
                }

                return $child;
            })
            ->values()
            ->all();

        $schema = self::buildComponents($prefixed);

        // Use Section if label/columns present (Section supports label & columns)
        if (! empty($label) || $columns) {
            $section = Components\Section::make($label ?? null)
                ->schema($schema);

            if ($columns) {
                $section->columns($columns);
            }

            return self::applyCommonComponentOptions($section, $item);
        }

        // Fallback: Group without label
        $group = Components\Group::make()
            ->schema($schema);

        return self::applyCommonComponentOptions($group, $item);
    }

    protected static function buildRelationship(array $item): Components\Section|Components\Group
    {
        $relation = $item['name'] ?? null;
        $label = $item['label'] ?? null;
        $columns = $item['columns'] ?? null;

        // Children schema as provided — DO NOT prefix names here.
        $rawChildren = $item['schema'] ?? [];

        // Normalize visibility conditions inside children:
        // If a condition references "relation.field" (e.g. "lead.email"),
        // strip the "lead." prefix so it works inside the child schema.
        $normalizedChildren = collect($rawChildren)
            ->map(function (array $child) use ($relation) {
                foreach (['visible_when', 'hidden_when'] as $condKey) {
                    if (empty($child[$condKey])) {
                        continue;
                    }

                    $original = $child[$condKey];

                    // Normalize to array
                    $conds = isset($original['field']) ? [$original] : $original;

                    $conds = array_map(function ($c) use ($relation) {
                        if (! empty($c['field']) && str_starts_with($c['field'], $relation.'.')) {
                            $c['field'] = substr($c['field'], strlen($relation) + 1);
                        }

                        return $c;
                    }, $conds);

                    // Put back single-object or array depending on original shape
                    $child[$condKey] = (isset($original['field']) && count($conds) === 1) ? $conds[0] : $conds;
                }

                return $child;
            })
            ->values()
            ->all();

        // Build schema components for children WITHOUT prefixing names
        $schema = self::buildComponents($normalizedChildren);

        // If label/columns present, use Section (Section supports relationship())
        if (! empty($label) || $columns) {
            $section = Components\Section::make($label ?? null)
                ->schema($schema)
                ->contained(false); // Not visually contained

            if ($columns) {
                $section->columns($columns);
            }

            // KEY: scope the section to the relationship so Filament loads/saves it
            $section->relationship($relation);

            return self::applyCommonComponentOptions($section, $item);
        }

        // Fallback: Group also supports relationship()
        $group = Components\Group::make()
            ->schema($schema);

        $group->relationship($relation);

        return self::applyCommonComponentOptions($group, $item);
    }

    protected static function buildGroup(array $item): Components\Section|Components\Group
    {
        $schema = self::buildComponents($item['schema'] ?? []);

        // If a label or columns are provided, prefer Section (it supports label & columns)
        if (! empty($item['label']) || ! empty($item['columns'])) {
            $section = Components\Section::make($item['label'] ?? null)
                ->schema($schema);

            if (! empty($item['columns'])) {
                $section->columns($item['columns']);
            }

            return self::applyCommonComponentOptions($section, $item);
        }

        // Otherwise use Group (no label)
        $group = Components\Group::make()
            ->schema($schema);

        return self::applyCommonComponentOptions($group, $item);
    }

    public static function buildComponent(array $item): ?Components\Component
    {
        if (
            ($item['sortable'] ?? false) &&
            isset($item['schema']) &&
            is_array($item['schema'])
        ) {
            usort(
                $item['schema'],
                fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0)
            );
        }
        $type = $item['component'] ?? null;

        return match ($type) {
            'section' => self::buildSection($item),
            'grid' => self::buildGrid($item),
            'tabs' => self::buildTabs($item),
            'wizard' => self::buildWizard($item),
            'repeater' => self::buildRepeater($item),
            'relationship' => self::buildRelationship($item),
            'group' => self::buildGroup($item),

            'textInput' => self::buildTextInput($item),
            'textEntry' => self::buildTextEntry($item),
            'imageEntry' => self::buildImageEntry($item),
            'textarea' => self::buildTextarea($item),
            'select' => self::buildSelect($item),
            'toggle' => self::buildToggle($item),
            'checkbox' => self::buildCheckbox($item),
            'datePicker' => self::buildDatePicker($item),
            'dateTimePicker' => self::buildDateTimePicker($item),
            'fileUpload' => self::buildFileUpload($item),
            'radio' => self::buildRadio($item),
            'checkboxList' => self::buildCheckboxList($item),
            'colorPicker' => self::buildColorPicker($item),
            'tagsInput' => self::buildTagsInput($item),
            'timePicker' => self::buildTimePicker($item),
            'address' => self::buildAddress($item),
            'addressEntry' => self::buildAddressEntry($item),
            'placeholder' => self::buildPlaceholder($item),
            'view' => self::buildView($item),
            'dragDrop' => self::buildDragDrop($item),

            default => null,
        };
    }

    /* ========== Layout components ========== */
    protected static function buildWizard(array $item): Components\Wizard
    {
        $steps = collect($item['steps'] ?? [])
            ->map(function (array $stepItem) {
                $schema = self::buildComponents($stepItem['schema'] ?? []);

                // If a relationship is specified at the step level, wrap the schema in a Group
                // to ensure Filament scopes the fields to that relationship.
                if (! empty($stepItem['relationship'])) {
                    $relationshipGroup = Components\Group::make()
                        ->schema($schema)
                        ->relationship($stepItem['relationship']);

                    $schema = [$relationshipGroup];
                }

                $step = Components\Wizard\Step::make($stepItem['label'] ?? null)
                    ->schema($schema);

                if (! empty($stepItem['description'])) {
                    $step->description($stepItem['description']);
                }

                if (! empty($stepItem['icon'])) {
                    $step->icon($stepItem['icon']);
                    $step->completedIcon($stepItem['icon']);
                }

                // Allow hiding specific steps based on logic
                // if (isset($stepItem['visible_when'])) {
                //     // Note: Steps technically support visibility, but typically handled via schema logic
                //     // We apply standard visibility if the Step component supports it, otherwise handled in schema
                // }

                return self::applyCommonComponentOptions($step, $stepItem);
            })
            ->values()
            ->all();

        $wizard = Components\Wizard::make($steps);

        if (! empty($item['start_on_step'])) {
            $wizard->startOnStep($item['start_on_step']);
        }

        if (! empty($item['skippable'])) {
            $wizard->skippable();
        }

        if (isset($item['persist_step_in_query_string'])) {
            // If passing a string key
            if (is_string($item['persist_step_in_query_string'])) {
                $wizard->persistStepInQueryString($item['persist_step_in_query_string']);
            }
            // If strictly boolean true (uses default key)
            elseif ($item['persist_step_in_query_string'] === true) {
                $wizard->persistStepInQueryString();
            }
        }

        return self::applyCommonComponentOptions($wizard, $item);
    }

    protected static function buildSection(array $item): Components\Section
    {
        $section = Components\Section::make($item['label'] ?? null)
            ->schema(self::buildComponents($item['schema'] ?? []));

        if (array_key_exists('columns', $item)) {
            $section->columns((int) $item['columns']);
        }

        if (! empty($item['description'])) {
            $section->description($item['description']);
        }

        if (! empty($item['icon'])) {
            $section->icon($item['icon']);
        }

        if (! empty($item['collapsible'])) {
            $section->collapsible();
        }

        if (! empty($item['collapsed'])) {
            $section->collapsed();
        }

        if (! empty($item['compact'])) {
            $section->compact();
        }

        return self::applyCommonComponentOptions($section, $item);
    }

    protected static function buildGrid(array $item): Components\Grid
    {
        $grid = Components\Grid::make($item['columns'] ?? 1)
            ->schema(self::buildComponents($item['schema'] ?? []));

        return self::applyCommonComponentOptions($grid, $item);
    }

    protected static function buildTabs(array $item): Components\Tabs
    {
        $tabs = collect($item['tabs'] ?? [])
            ->map(function (array $tabItem) {
                return Components\Tabs\Tab::make($tabItem['label'] ?? '')
                    ->schema(JsonFormBuilder::buildComponents($tabItem['schema'] ?? []));
            })
            ->values()
            ->all();

        // Give Tabs::make() an optional label (or leave empty)
        $tabsComponent = Components\Tabs::make($item['label'] ?? null)
            ->tabs($tabs);

        return self::applyCommonComponentOptions($tabsComponent, $item);
    }

    protected static function buildRepeater(array $item): Repeater
    {
        $repeater = Repeater::make($item['name'])
            ->label($item['label'] ?? null)
            ->schema(self::buildComponents($item['schema'] ?? []))
            ->columns($item['columns'] ?? 1);

        if (! empty($item['item_label'])) {
            $repeater->itemLabel($item['item_label']);
        }
        if (! empty($item['reorderable'])) {
            $repeater->reorderable();
        }

        if (! empty($item['reorderable_with_buttons'])) {
            $repeater->reorderableWithButtons();
        }

        return self::applyCommonFieldOptions($repeater, $item);
    }

    protected static function buildPlaceholder(array $item): Forms\Components\Placeholder
    {
        $field = Forms\Components\Placeholder::make($item['name'])
            ->label($item['label'] ?? null);

        if (! empty($item['content'])) {
            $field->content($item['content']);
        }

        if (! empty($item['content_hook'])) {
            $hookString = $item['content_hook'];
            $field->content(function ($record, $get) use ($hookString) {
                if (str_contains($hookString, '@')) {
                    [$class, $method] = explode('@', $hookString);

                    return $class::$method($record, $get);
                }

                return 'Hook error';
            });
        }

        return self::applyCommonComponentOptions($field, $item);
    }

    protected static function buildAddress(array $item): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($item['name'])
            ->label($item['label'] ?? null);
    }

    protected static function buildAddressEntry(array $item): TextEntry
    {
        $fieldName = $item['name'];
        return TextEntry::make($fieldName)
            ->label($item['label'] ?? null)
            ->formatStateUsing(function ($state, $record) use ($fieldName) {
                if (! $record) {
                    return '—';
                }
                $parts = array_filter([
                    $record->{$fieldName . '_street1'} ?? null,
                    $record->{$fieldName . '_street2'} ?? null,
                    $record->{$fieldName . '_city'} ?? null,
                    $record->{$fieldName . '_state'} ?? null,
                    $record->{$fieldName . '_pincode'} ?? null,
                ]);
                return $parts ? implode(', ', $parts) : '—';
            });
    }

    protected static function buildView(array $item)
    {
        if (! empty($item['name'])) {
            $field = Forms\Components\ViewField::make($item['name'])
                ->view($item['view'])
                ->label($item['label'] ?? null);

            if (! empty($item['view_data']) && is_array($item['view_data'])) {
                $field->viewData($item['view_data']);
            }

            if (! empty($item['relationship'])) {
                $relationName = $item['relationship'];

                // Hydrate the field state from the relationship
                $field->afterStateHydrated(function ($component, $record) use ($relationName) {
                    if ($record && method_exists($record, $relationName)) {
                        $ids = $record->{$relationName}()->pluck('dealer_id')->toArray();
                        $component->state($ids);
                    }
                });

                $field->saveRelationshipsUsing(function ($state, $record) use ($relationName) {
                    if ($record && method_exists($record, $relationName)) {
                        // Use sync to handle the many-to-many relationship
                        // We map IDs to an array with updated_by to force the pivot update event
                        $userId = auth()->id();
                        $syncData = [];
                        foreach ($state ?? [] as $id) {
                            if (empty($id)) {
                                continue;
                            }
                            $syncData[$id] = ['updated_by' => $userId];
                        }

                        $record->{$relationName}()->sync($syncData);
                    }
                });

                // Keep dehydrated(true) so the state is sent to the relationship saver
                $field->dehydrated(true);
            }

            return self::applyCommonFieldOptions($field, $item);
        }

        // Fallback or generic view (if View class exists, which it seems not to based on error)
        // Check if View class exists to avoid crash
        if (class_exists(Forms\Components\View::class)) {
            $field = Forms\Components\View::make($item['view'])
                ->label($item['label'] ?? null);

            return self::applyCommonComponentOptions($field, $item);
        }

        // If View class missing, return a Placeholder? Or throw helpful error?
        // For now, assuming ViewField covers the user request.
        // We fallback to a placeholder saying View not supported if name missing?
        return Forms\Components\Placeholder::make('view_error')
            ->content('Error: View component not found and no name provided for ViewField.');
    }

    protected static function buildDragDrop(array $item): Forms\Components\ViewField
    {
        $field = Forms\Components\ViewField::make($item['name'])
            ->view('filament.components.drag-drop-transfer')
            ->label($item['label'] ?? null);

        $viewData = [
            'repeaterName'      => $item['repeater_name']       ?? null,
            'source'            => $item['source']              ?? 'repeater',
            'dependsOn'         => $item['depends_on']          ?? null,
            'ownerModuleId'     => $item['owner_module_id']     ?? null,
            'ownerModuleFields' => $item['owner_module_fields'] ?? null,
        ];

        $field->viewData($viewData);

        return self::applyCommonFieldOptions($field, $item);
    }

    /* ========== Field components ========== */

    protected static function buildTextEntry(array $item): TextEntry
    {
        $field = TextEntry::make($item['name'])
            ->label($item['label'] ?? null);
        // color
        if (! empty($item['color'])) {
            $field->color($item['color']);
        }
        // badge
        if (isset($item['badge']) && $item['badge']) {
            $field->badge();
        }
        // formatStateUsing -> callback
        if (! empty($item['formatStateUsing'])) {
            $callbackString = $item['formatStateUsing'];
            $field->formatStateUsing(function (?string $state) use ($callbackString) {
                if ($state === null) {
                    return null;
                }
                return app()->call($callbackString, ['state' => $state]);
            });
        }
        // inline
        if (isset($item['inlineLabel']) && $item['inlineLabel']) {
            $field->inlineLabel();
        }
        // size
        if (! empty($item['size'])) {
            $sizeEnum = match ($item['size']) {
                'xs' => TextSize::ExtraSmall,
                'sm' => TextSize::Small,
                'md' => TextSize::Medium,
                'lg' => TextSize::Large,
                default => TextSize::Medium
            };
            $field->size($sizeEnum);
        }
        // date
        if (isset($item['date']) && $item['date']) {
            if (is_bool($item['date'])) {
                $field->date();
            } else {
                $field->date($item['date']);
            }
        }
        // dateTime
        if (isset($item['dateTime']) && $item['dateTime']) {
            if (is_bool($item['dateTime'])) {
                $field->dateTime();
            } else {
                $field->dateTime($item['dateTime']);
            }
        }
        // time
        if (isset($item['time']) && $item['time']) {
            if (is_bool($item['time'])) {
                $field->time();
            } else {
                $field->time($item['time']);
            }
        }
        // numeric
        if (isset($item['numeric']) && $item['numeric']) {
            $field->numeric();
        }
        // money
        if (isset($item['money']) && $item['money']) {
            $field->money('inr');
        }
        // weight
        if (! empty($item['weight'])) {
            $weightEnum = match ($item['weight']) {
                'thin' => FontWeight::Thin,
                'extralight' => FontWeight::ExtraLight,
                'light' => FontWeight::Light,
                'normal' => FontWeight::Normal,
                'medium' => FontWeight::Medium,
                'semibold' => FontWeight::SemiBold,
                'bold' => FontWeight::Bold,
                'extrabold' => FontWeight::ExtraBold,
                'black' => FontWeight::Black,
                default => FontWeight::Normal
            };

            $field->weight($weightEnum);
        }

        // Handle dropdown value mapping
        $stateFormatterApplied = false;
        if (! empty($item['dropdown'])) {
            $dropdownType = $item['dropdown'];
            $field->formatStateUsing(function ($state) use ($dropdownType) {
                $options = DropdownHandler::getStudioDom($dropdownType);
                return $options[$state] ?? $state;
            });
            $stateFormatterApplied = true;
        }

        // Relate: resolve stored ID to the related record's display label
        if (($item['options_source'] ?? null) === 'relate' && ! empty($item['relate_module'])) {
            $relateModule = $item['relate_module'];
            $modelClass   = 'App\\Models\\' . Str::studly($relateModule);
            if (! class_exists($modelClass)) {
                $modelClass = 'App\\Models\\' . Str::studly(Str::singular($relateModule));
            }
            if (class_exists($modelClass) && ! $stateFormatterApplied) {
                $displayField = self::resolveRelateDisplayColumn($modelClass, $item['display_field'] ?? 'name');
                $field->formatStateUsing(function ($state) use ($modelClass, $displayField) {
                    if ($state === null || $state === '') {
                        return '—';
                    }
                    $record = $modelClass::find($state);
                    if (! $record) {
                        return (string) $state;
                    }
                    if ($displayField === '__full_name__') {
                        return trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: (string) $state;
                    }
                    return (string) ($record->{$displayField} ?? $state);
                });
            }
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildImageEntry(array $item): ImageEntry
    {
        $field = ImageEntry::make($item['name'])
            ->label($item['label'] ?? null);

        if (! empty($item['disk'])) {
            $field->disk($item['disk']);
        }
        if (! empty($item['height'])) {
            $field->height($item['height']);
        }
        if (! empty($item['circular'])) {
            $field->circular();
        }

        return $field;
    }

    protected static function buildTextInput(array $item): TextInput
    {
        $field = TextInput::make($item['name'])
            ->label($item['label'] ?? null);

        if (($item['type'] ?? null) === 'numeric') {
            $field->numeric();
        }
        if (($item['type'] ?? null) === 'password') {
            $field->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->afterStateUpdated(function ($livewire, $component) {
                    $statePath = $component->getStatePath();
                    $livewire->validateOnly($statePath);

                    if (! str_ends_with($statePath, '_confirmation')) {
                        $livewire->validateOnly($statePath.'_confirmation');
                    }
                });
        }

        if (! empty($item['unique'])) {
            $field->unique()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($livewire, TextInput $component) {
                    $livewire->validateOnly($component->getStatePath());
                });
        }

        if (! empty($item['unique_where_role'])) {
            $roleName = $item['unique_where_role'];
            $fieldName = $item['name'];
            $field->rules([
                fn ($get) => Rule::unique('users', $fieldName)
                    ->where(function ($query) use ($roleName) {
                        $query->whereIn('id', function ($sub) use ($roleName) {
                            $sub->select('model_id')
                                ->from('model_has_roles')
                                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                                ->where('roles.name', $roleName)
                                ->where('model_has_roles.model_type', 'App\\Models\\User');
                        });
                    })
                    ->ignore($get('id')),
            ])
                ->live(onBlur: true)
                ->afterStateUpdated(function ($livewire, TextInput $component) {
                    $livewire->validateOnly($component->getStatePath());
                });
        }

        if (! empty($item['same'])) {
            $field->same($item['same']);
        }

        if (! empty($item['rules']) && ($item['type'] ?? null) === 'password') {
            $field->rules([
                Password::min((int) $item['rules'])
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ]);
        }

        if (($item['type'] ?? null) === 'email') {
            $field->email();
        }

        if (($item['type'] ?? null) === 'url') {
            $field->url();
        }

         $messages = $item['messages'] ?? null;

        if (! empty($messages)) {
            $field->validationMessages($messages);
        }

        $validationRules = $item['validation'] ?? [];

        if (! empty($validationRules)) {
            if (! empty($item['strict_messages'])) {
                $rules = $validationRules;
                $customMessages = $messages ?? [];

                    $field->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) use ($rules, $customMessages) {
                            foreach ($rules as $rule) {
                                // nullable: empty value passes all remaining rules
                                if ($rule === 'nullable' && ($value === null || $value === '')) {
                                    return;
                                }

                                [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);

                                $failed = match ($ruleName) {
                                    'max'      => mb_strlen((string) $value) > (int) $ruleParam,
                                    'min'      => mb_strlen((string) $value) < (int) $ruleParam,
                                    'regex'    => $value !== null && $value !== '' && ! preg_match($ruleParam, (string) $value),
                                    'required' => $value === null || $value === '',
                                    'string'   => ! is_string($value),
                                    'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) === false,
                                    'url'      => filter_var($value, FILTER_VALIDATE_URL) === false,
                                    'integer', 'biginteger', 'bigint', 'number', 'int'
                                               => ! (is_numeric($value) && floor((float) $value) == $value),
                                    'date', 'datetime', 'timestamp'
                                               => $value !== null && $value !== '' && strtotime($value) === false,
                                    'json'     => $value !== null && $value !== '' && (static function () use ($value): bool {
                                        json_decode($value);
                                        return json_last_error() !== JSON_ERROR_NONE;
                                    })(),
                                    'nullable' => false,
                                    default    => false,
                                };

                                if ($failed) {
                                    $fail($customMessages[$ruleName] ?? "The {$attribute} is invalid.");
                                    return;
                                }
                            }
                        },
                    ]);
            } 
            else {
                    $field->rules($validationRules);
                }
        }

        /*
            "live": true,
            "duplicate": ["leads","mobile"],
        */

        if (! empty($item['duplicate']) && is_array($item['duplicate'])) {
            $table  = $item['duplicate'][0];
            $column = $item['duplicate'][1];
            $field->rules([
                fn ($record) => Rule::unique($table, $column)->ignore($record?->getKey()),
            ])->live(onBlur: true);
        }

        if (! empty($item['live'])) {
            $field->live(debounce: 500)->afterStateUpdated(function ($livewire, $component) {
                $statePath = $component->getStatePath();
                $livewire->validateOnly($statePath);
            });
        }

        if ($max = $item['maxLength'] ?? null) {
            $field->maxLength($max);
        }

        if ($min = $item['min_value'] ?? null) {
            $field->minValue($min);
        }

        if ($maxVal = $item['max_value'] ?? null) {
            $field->maxValue($maxVal);
        }

        // ------------------------------
        // Conditional disabling logic
        // ------------------------------

        self::applyPopulationOptions($field, $item);

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildCheckboxList(array $item): Forms\Components\CheckboxList
    {
        $field = Forms\Components\CheckboxList::make($item['name'])
            ->label($item['label'] ?? null);

        if (! empty($item['relationship'])) {
            $relationship = $item['relationship'];
            $titleColumn = $item['title_column'] ?? 'name';

            $modifyQuery = null;
            if (! empty($item['modify_query_hook'])) {
                $hookString = $item['modify_query_hook'];
                $modifyQuery = function ($query, $get) use ($hookString) {
                    if (str_contains($hookString, '@')) {
                        [$class, $method] = explode('@', $hookString);

                        return $class::$method($query, $get);
                    }

                    return $query;
                };
            }

            $field->relationship($relationship, $titleColumn, $modifyQuery);
        }

        if (! empty($item['searchable'])) {
            $field->searchable();
        }

        if (! empty($item['bulk_toggleable'])) {
            $field->bulkToggleable();
        }

        if (! empty($item['columns'])) {
            $field->columns((int) $item['columns']);
        }

        if (! empty($item['grid_direction'])) {
            $field->gridDirection($item['grid_direction']);
        }

        if (! empty($item['label_hook'])) {
            $hookString = $item['label_hook'];
            $field->getOptionLabelFromRecordUsing(function ($record) use ($hookString) {
                if (str_contains($hookString, '@')) {
                    [$class, $method] = explode('@', $hookString);

                    return $class::$method($record);
                }

                return $record->name;
            });
        }

        $field->options(self::resolveStaticOptions($item));

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildTextarea(array $item): Forms\Components\Textarea
    {
        $field = Forms\Components\Textarea::make($item['name'])
            ->label($item['label'] ?? null);

        if ($rows = $item['rows'] ?? null) {
            $field->rows($rows);
        }

        $messages        = $item['messages'] ?? [];
        $validationRules = $item['validation'] ?? [];

        if (! empty($validationRules)) {
            if (! empty($item['strict_messages'])) {
                $rules          = $validationRules;
                $customMessages = $messages;

                $field->rules([
                    fn (): \Closure => function (string $attribute, $value, \Closure $fail) use ($rules, $customMessages) {
                        foreach ($rules as $rule) {
                            if ($rule === 'nullable' && ($value === null || $value === '')) {
                                return;
                            }

                            [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);

                            $failed = match ($ruleName) {
                                'max'      => mb_strlen((string) $value) > (int) $ruleParam,
                                'min'      => mb_strlen((string) $value) < (int) $ruleParam,
                                'regex'    => $value !== null && $value !== '' && ! preg_match($ruleParam, (string) $value),
                                'required' => $value === null || $value === '',
                                'string'   => ! is_string($value),
                                'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) === false,
                                'url'      => filter_var($value, FILTER_VALIDATE_URL) === false,
                                'integer', 'biginteger', 'bigint', 'number', 'int'
                                           => ! (is_numeric($value) && floor((float) $value) == $value),
                                'date', 'datetime', 'timestamp'
                                           => $value !== null && $value !== '' && strtotime($value) === false,
                                'json'     => $value !== null && $value !== '' && (static function () use ($value): bool {
                                    json_decode($value);
                                    return json_last_error() !== JSON_ERROR_NONE;
                                })(),
                                'nullable' => false,
                                default    => false,
                            };

                            if ($failed) {
                                $fail($customMessages[$ruleName] ?? "The {$attribute} is invalid.");
                                return;
                            }
                        }
                    },
                ]);
            } else {
                $field->rules($validationRules);
            }
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildSelect(array $item): Forms\Components\Select
    {
        $field = Forms\Components\Select::make($item['name'])
            ->label($item['label'] ?? null);

        if (! empty($item['multiple'])) {
            $field->multiple();
        }
        if (! empty($item['searchable'])) {
            $field->searchable();
        }
        if (! empty($item['preload'])) {
            $field->preload();
        }
        if (isset($item['selectable_placeholder'])) {
            $field->selectablePlaceholder($item['selectable_placeholder']);
        }

        $source = $item['options_source'] ?? 'static';

        match ($source) {
            'static' => $field->options($item['options'] ?? []),
            'relationship' => self::applyRelationshipOptions($field, $item),
            'relate' => self::applyRelateOptions($field, $item),
            'eloquent' => self::applyEloquentOptions($field, $item),
            'helper' => self::applyHelperOptions($field, $item),
            'enum' => self::applyEnumOptions($field, $item),
            'config' => self::applyConfigOptions($field, $item),
            default => null,
        };

        // Clear dependent fields on update
        if (! empty($item['clear_on_update']) && is_array($item['clear_on_update'])) {
            $targets = $item['clear_on_update'];

            $field->live()->afterStateUpdated(function ($state, Set $set) use ($targets) {
                foreach ($targets as $name) {
                    $set($name, null);
                }
            });
        }

        self::applyPopulationOptions($field, $item);

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildRadio(array $item): Forms\Components\Radio
    {
        $field = Forms\Components\Radio::make($item['name'])
            ->label($item['label'] ?? null);
        // inline
        if (! empty($item['inline'])) {
            $field->inline();
        }

        if (! empty($item['messages'])) {
            $field->validationMessages($item['messages']);
        }

        $field->options(self::resolveStaticOptions($item));
        if (! empty($item['clear_on_update']) && is_array($item['clear_on_update'])) {
            $targets = $item['clear_on_update'];

            $field->live()->afterStateUpdated(function ($state, Set $set) use ($targets) {
                foreach ($targets as $name) {
                    $set($name, null);
                }
            });
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function applyRelationshipOptions(Forms\Components\Select $field, array $item): void
    {
        $relationship = $item['relationship'] ?? null;
        $titleColumn = $item['title_column'] ?? 'name';
        $storeStringValue = $item['store_string_value'] ?? false;

        $searchableColumns = $item['searchable_columns'] ?? null;

        if ($relationship) {
            if (empty($item['multiple'])) {
                // BUG FIX: Array to string conversion
                // For single-select many-to-many, we MUST NOT call relationship() directly
                // as it triggers default hydration which crashes on Collection -> scalar cast.

                // 1. Manually fetch options
                $field->options(function (Forms\Components\Select $component) use ($relationship, $titleColumn, $storeStringValue) {
                    $modelClass = $component->getContainer()->getModel();
                    if (! class_exists($modelClass)) {
                        return [];
                    }

                    // Instantiate model to get the relationship definition
                    $model = new $modelClass;
                    if (! method_exists($model, $relationship)) {
                        return [];
                    }

                    // Key by Name if storeStringValue is true, otherwise ID
                    $keyColumn = $storeStringValue ? $titleColumn : 'id';
                    // Checks if title_column contains curly braces like {first_name}
                    if (preg_match('/\{(.+?)\}/', $titleColumn)) {
                        $query = $model->{$relationship}()->getRelated();

                        return $query->get()->mapWithKeys(function ($record) use ($titleColumn, $keyColumn) {
                            $label = preg_replace_callback('/\{(.+?)\}/', function ($matches) use ($record) {
                                $column = $matches[1];

                                return $record->{$column} ?? '';
                            }, $titleColumn);

                            return [$record->{$keyColumn} => trim($label)];
                        });
                    }
                    // Filter ONLY when relationship is roles
                    if ($relationship === 'roles') {
                        $relatedQuery = $model->{$relationship}()->getRelated()::query();
                        $user = auth()->user();
                        $allowedRoles = allowedRoleNames($user);
                        if (empty($allowedRoles)) {
                            return [];
                        }
                        if (! empty($allowedRoles) && ! empty($relatedQuery)) {
                            $relatedQuery->whereIn('name', $allowedRoles);

                            return $relatedQuery->pluck($titleColumn, $keyColumn);
                        }
                    }

                    return $model->{$relationship}()->getRelated()->pluck($titleColumn, $keyColumn);
                });

                // If template is used, we must tell Filament how to search the specific columns
                if ($searchableColumns && is_array($searchableColumns)) {
                    $field->searchable($searchableColumns);
                } elseif (preg_match('/\{(.+?)\}/', $titleColumn)) {
                    // Fallback: If no searchable_columns defined, extract them from the template braces
                    preg_match_all('/\{(.+?)\}/', $titleColumn, $matches);
                    $field->searchable($matches[1] ?? []);
                }
                // 2. Manually load state
                $field->afterStateHydrated(function (Forms\Components\Select $component) use ($relationship, $titleColumn, $storeStringValue) {
                    $record = $component->getContainer()->getRecord();
                    if (! $record) {
                        return;
                    }

                    $relatedRecord = $record->{$relationship}()->first();
                    if (! $relatedRecord) {
                        return;
                    }

                    // Set state to Name if storeStringValue is true, otherwise Key (ID)
                    $state = $storeStringValue ? $relatedRecord->{$titleColumn} : $relatedRecord->getKey();
                    $component->state($state);
                });

                // 3. Manually save state
                $field->saveRelationshipsUsing(static function (Forms\Components\Select $component, $state) use ($relationship, $titleColumn, $storeStringValue) {

                    $record = $component->getContainer()->getRecord();
                    if (! $record) {
                        return;
                    }

                    // Resolve ID either from string or direct input
                    $idToSync = $state;

                    if ($storeStringValue && $state) {
                        $relatedModel = $record->{$relationship}()->getRelated();
                        $found = $relatedModel->where($titleColumn, $state)->first();
                        $idToSync = $found?->getKey();
                    }

                    // Detect relationship type
                    $relation = $record->{$relationship}();

                    // -----------------------------
                    // 1) BelongsTo → associate
                    // -----------------------------
                    if ($relation instanceof BelongsTo) {
                        $record->{$relation->getForeignKeyName()} = $idToSync;
                        $record->save();

                        return;
                    }

                    // -----------------------------
                    // 2) BelongsToMany / MorphToMany → sync
                    // -----------------------------
                    if (
                        $relation instanceof BelongsToMany ||
                        $relation instanceof MorphToMany
                    ) {
                        $relation->sync($idToSync ? [$idToSync] : []);

                        return;
                    }

                    // -----------------------------
                    // 3) Unexpected relation → skip safely
                    // -----------------------------
                    // (no sync required for other types)
                });

                // Ensure hydrated state is not overwritten by default behavior
                $field->dehydrated(false);
                if (preg_match('/\{(.+?)\}/', $titleColumn)) {
                    $field->getOptionLabelFromRecordUsing(function ($record) use ($titleColumn) {
                        return preg_replace_callback('/\{(.+?)\}/', function ($matches) use ($record) {
                            return $record->{$matches[1]} ?? '';
                        }, $titleColumn);
                    });
                }
            } else {
                $field->relationship($relationship, $titleColumn);
                if (preg_match('/\{(.+?)\}/', $titleColumn)) {
                    $field->getOptionLabelFromRecordUsing(function ($record) use ($titleColumn) {
                        return preg_replace_callback('/\{(.+?)\}/', function ($matches) use ($record) {
                            return $record->{$matches[1]} ?? '';
                        }, $titleColumn);
                    });
                }
            }
        }
    }

    protected static function applyEloquentOptions(Forms\Components\Select $field, array $item): void
    {
        $modelClass = $item['model'] ?? null;
        $valueColumn = $item['value_column'] ?? 'id';
        $labelColumn = $item['label_column'] ?? 'name';
        $whereConfig = $item['where'] ?? [];

        $field->options(function (Get $get) use ($modelClass, $valueColumn, $labelColumn, $whereConfig) {
            if (! $modelClass || ! class_exists($modelClass)) {
                return [];
            }

            $query = $modelClass::query();

            foreach ($whereConfig as $where) {
                $column = $where['column'] ?? null;
                $dependsOn = $where['depends_on'] ?? null;

                if (! $column || ! $dependsOn) {
                    continue;
                }

                $value = $get($dependsOn);

                if ($value === null || $value === '') {
                    // no parent value → no options
                    return [];
                }

                $query->where($column, $value);
            }

            return $query->pluck($labelColumn, $valueColumn)->toArray();
        });
    }

    // Resolves options for non-Select components (Radio, CheckboxList) that
    // support options_source: 'helper' but can't use applyHelperOptions() directly.
    protected static function resolveStaticOptions(array $item): array
    {
        if (($item['options_source'] ?? 'static') === 'helper') {
            $class  = $item['helper_class'] ?? null;
            $method = $item['helper_method'] ?? null;
            $params = $item['helper_params'] ?? [];
            if ($class && $method && class_exists($class) && method_exists($class, $method)) {
                $opts = $class::$method(...$params);
                return is_array($opts) ? $opts : [];
            }
            return [];
        }
        return is_array($item['options'] ?? null) ? $item['options'] : [];
    }

    protected static function applyHelperOptions(Forms\Components\Select $field, array $item): void
    {
        $helperClass = $item['helper_class'] ?? null;
        $helperMethod = $item['helper_method'] ?? null;
        $helperType = $item['helper_type'] ?? 'static';
        $helperParams = $item['helper_params'] ?? [];
        /*if(count($helperParams) > 0){
            $helperType = 'dynamic';
        }*/
        // New style: class + method in JSON
        if ($helperClass && $helperMethod) {
            // Static helper: no dependency on other fields
            if ($helperType === 'static') {
                if (class_exists($helperClass) && method_exists($helperClass, $helperMethod)) {
                    // Call once, no $get
                    // $options = $helperClass::$helperMethod();
                    $options = $helperClass::$helperMethod(...$helperParams);
                    if (is_array($options)) {
                        $field->options($options);
                    } else {
                        $field->options(['' => 'No Options Found']);
                    }
                }

                return;
            }

            // Dynamic helper: depends on other fields (via $get)
            // $field->options(function (Get $get) use ($helperClass, $helperMethod, $helperParams) {
            $field->options(function (Get $get) use ($helperClass, $helperMethod, $helperParams, $helperType) {
                if (! class_exists($helperClass) || ! method_exists($helperClass, $helperMethod)) {
                    return [];
                }

                $args = [];

                foreach ($helperParams as $param) {
                    // Hybrid param
                    if ($helperType == 'hybrid') {
                        $value = (is_string($param) && str_starts_with($param, '@')) ? $get(substr($param, 1)) : $param;
                    } else {
                        $value = $get($param);
                    }

                    // If any dependency is empty → no options
                    if ($value === null || $value === '') {
                        // return [];
                    }

                    $args[] = $value;
                }

                $result = $helperClass::$helperMethod(...$args);

                // return is_array($result) ? $result : [];
                // Ensure every key and value is a string, and remove nulls.
                return collect(is_array($result) ? $result : [])
                    ->mapWithKeys(function ($value, $key) {
                        return [(string) $key => (string) ($value ?? $key)];
                    })
                    ->toArray();
            });

            if (! empty($item['auto_select_single'])) {
                $resolveSingleOption = function () use ($helperClass, $helperMethod, $helperParams, $helperType): mixed {
                    if (! class_exists($helperClass) || ! method_exists($helperClass, $helperMethod)) {
                        return null;
                    }
                    // If any param is @-prefixed (reactive dependency), we cannot resolve
                    // without the live form context — skip auto-select entirely.
                    if ($helperType === 'hybrid') {
                        foreach ($helperParams as $param) {
                            if (is_string($param) && str_starts_with($param, '@')) {
                                return null;
                            }
                        }
                    }
                    $args = array_map(
                        fn ($param) => ($helperType === 'hybrid' && is_string($param) && str_starts_with($param, '@')) ? null : $param,
                        $helperParams
                    );
                    $result = $helperClass::$helperMethod(...$args);
                    // Cast keys to string to match how options() closure normalises them
                    $options = collect(is_array($result) ? $result : [])
                        ->mapWithKeys(fn ($v, $k) => [(string) $k => (string) ($v ?? $k)])
                        ->toArray();

                    return count($options) === 1 ? array_key_first($options) : null;
                };

                // Create form: set default so the field is pre-filled on load
                $field->default($resolveSingleOption);

                $field->afterStateHydrated(function ($component, $state, $livewire) use ($resolveSingleOption) {
                    if (! empty($state)) {
                        return;
                    }
                    $single = $resolveSingleOption();
                    if ($single !== null) {
                        data_set($livewire, $component->getStatePath(), $single);
                    }
                });
            }

            return;
        }

        // Old style (with helper_key + DynamicFormOptions) still supported as fallback
        $helperKey = $item['helper_key'] ?? null;

        if (! $helperKey) {
            return;
        }

        $field->options(function (Get $get) use ($helperKey) {
            return DynamicFormOptions::for($helperKey, $get);
        });
    }

    protected static function applyEnumOptions(Forms\Components\Select $field, array $item): void
    {
        $enumClass = $item['enum_class'] ?? null;

        if (! $enumClass || ! enum_exists($enumClass)) {
            return;
        }

        $options = collect($enumClass::cases())
            ->mapWithKeys(fn ($case) => [
                $case->value => $case->name,
            ])
            ->toArray();

        $field->options($options);
    }

    protected static function applyConfigOptions(Forms\Components\Select $field, array $item): void
    {
        $key = $item['config_key'] ?? null;

        if (! $key) {
            return;
        }

        $options = Config::get($key, []);
        $field->options($options);
    }

    protected static function buildToggle(array $item): Forms\Components\Toggle
    {
        $field = Forms\Components\Toggle::make($item['name'])
            ->label($item['label'] ?? null);

        if (isset($item['inline'])) {
            $field->inline($item['inline']);
        }
        if (! empty($item['clear_on_update']) && is_array($item['clear_on_update'])) {
            $targets = $item['clear_on_update'];

            $field->live(onBlur: true)->afterStateUpdated(function ($state, Set $set) use ($targets) {
                foreach ($targets as $name) {
                    $set($name, null);
                }
            });
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildCheckbox(array $item): Forms\Components\Checkbox
    {
        $field = Forms\Components\Checkbox::make($item['name'])
            ->label($item['label'] ?? null);
        if (! empty($item['clear_on_update']) && is_array($item['clear_on_update'])) {
            $targets = $item['clear_on_update'];

            $field->live()->afterStateUpdated(function ($state, Set $set) use ($targets) {
                foreach ($targets as $name) {
                    $set($name, null);
                }
            });
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildDatePicker(array $item): Forms\Components\DatePicker
    {
        $field = Forms\Components\DatePicker::make($item['name'])
            ->native($item['native'] ?? false)
            ->displayFormat($item['display_format'] ?? 'd-m-Y')
            ->format('Y-m-d')
            ->label($item['label'] ?? null);
        // close_on_date_selection
        $closeOnDateSelection = $item['close_on_date_selection'] ?? true;
        if ($closeOnDateSelection) {
            $field->closeOnDateSelection();
        }

        if (! empty($item['messages'])) {
            $field->validationMessages($item['messages']);
        }

        if (! empty($item['min_date'])) {
            $field->minDate(self::resolveDateValue($item['min_date']));
        }

        if (! empty($item['min_date_create_only'])) {
            $minDateValue = self::resolveDateValue($item['min_date_create_only']);
            $field->minDate(fn ($record) => $record === null ? $minDateValue : null);
        }

        if (! empty($item['min_date_field'])) {
            $field->minDate(fn ($get) => $get($item['min_date_field']));
        }

        if (! empty($item['max_date'])) {
            $field->maxDate(self::resolveDateValue($item['max_date']));
        }

        if (! empty($item['max_date_field'])) {
            $field->maxDate(fn ($get) => $get($item['max_date_field']));
        }
        if (! empty($item['default'])) {
            $field->default(self::resolveDateValue($item['default']));
        }

        // before_or_equal
        if (isset($item['before_or_equal'])) {
            $callbackString = $item['before_or_equal'];
            $field->beforeOrEqual(function ($record, $get) use ($callbackString) {
                return app()->call($callbackString, [
                    'get' => $get,
                    'record' => $record,
                ]);
            });
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function resolveDateValue(string $value): Carbon|string
    {
        return match (true) {
            $value === 'today' => Carbon::today()->endOfDay(),
            $value === 'now' => Carbon::now(),
            str_starts_with($value, '+'),
            str_starts_with($value, '-') => Carbon::parse($value),
            default => Carbon::parse($value),
        };
    }

    protected static function matchCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '!='       => $actual != $expected,
            '>'        => $actual > $expected,
            '>='       => $actual >= $expected,
            '<'        => $actual < $expected,
            '<='       => $actual <= $expected,
            'in'       => in_array($actual, (array) $expected),
            'not_in'   => ! in_array($actual, (array) $expected),
            'is_null'  => $actual === null || $actual === '',
            'not_null' => $actual !== null && $actual !== '',
            default    => $actual == $expected,  // '='
        };
    }

    protected static function buildDateTimePicker(array $item): Forms\Components\DateTimePicker
    {
        $field = Forms\Components\DateTimePicker::make($item['name'])
            ->native($item['native'] ?? false)
            ->displayFormat($item['display_format'] ?? 'd-m-Y H:i:s')
            ->label($item['label'] ?? null);
        $closeOnDateSelection = $item['close_on_date_selection'] ?? true;
        if ($closeOnDateSelection) {
            $field->closeOnDateSelection();
        }
        if (! empty($item['min_date'])) {
            $field->minDate(self::resolveDateValue($item['min_date']));
        }

        if (! empty($item['min_date_create_only'])) {
            $minDateValue = self::resolveDateValue($item['min_date_create_only']);
            $field->minDate(fn ($record) => $record === null ? $minDateValue : null);
        }

        if (! empty($item['min_date_field'])) {
            $field->minDate(fn ($get) => $get($item['min_date_field']));
        }

        if (! empty($item['max_date'])) {
            $field->maxDate(self::resolveDateValue($item['max_date']));
        }

        if (! empty($item['max_date_field'])) {
            $field->maxDate(fn ($get) => $get($item['max_date_field']));
        }
        if (! empty($item['default'])) {
            $field->default(self::resolveDateValue($item['default']));
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildFileUpload(array $item): Forms\Components\FileUpload
    {
        $field = Forms\Components\FileUpload::make($item['name'])
            ->label($item['label'] ?? null);

        if (! empty($item['avatar'])) {
            $field->avatar();
        }
        if (! empty($item['multiple'])) {
            $field->multiple();
        }
        // ── Client-side FilePond MIME restriction ─────────────────────────────
        if (! empty($item['accepted_file_types'])) {
            if (! is_array($item['accepted_file_types'])) {
                $item['accepted_file_types'] = explode(',', $item['accepted_file_types']);
            }
            $field->acceptedFileTypes($item['accepted_file_types']);
        } else {
            // Runtime defaults for modules that pre-date the file-validation config.
            if (! empty($item['image'])) {
                $field->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp']);
            } else {
                $field->acceptedFileTypes([
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain', 'text/csv',
                    'application/zip', 'application/x-zip-compressed',
                    'application/json',
                ]);
            }
        }

        // ── Server-side validation (always enforced, bypasses client-side FilePond) ──
        // Pull rules from the JSON config (set by LayoutGenerator::applyFieldValidations).
        // Fall back to safe defaults for modules not yet rebuilt. Filter 'nullable' —
        // that is handled by the required flag, not passed to Filament rules().
        $serverRules = array_values(array_filter(
            $item['validation'] ?? [],
            fn ($r) => $r !== 'nullable'
        ));

        if (empty($serverRules)) {
            // Default rules when no JSON config present yet.
            $serverRules = ! empty($item['image'])
                ? ['mimes:jpg,jpeg,png,gif,webp,svg,bmp']
                : ['mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,json'];
        }

        $field->rules($serverRules);
        $disk = isset($item['disk']) ? $item['disk'] : 's3';

        /* Dev-only override: when STOCKS_PHOTO_DISK_OVERRIDE is set in .env,
        // legacy-flagged photo fields (Stocks) save to that disk instead of s3.
        // Lets local dev work without AWS credentials. Visibility is forced to
        // public because the local driver has no temporaryUrl support.
        if (!empty($item['legacy_url_passthrough']) && filled(env('STOCKS_PHOTO_DISK_OVERRIDE'))) {
            $disk = env('STOCKS_PHOTO_DISK_OVERRIDE');
            $item['visibility'] = 'public';
        }  */

        $field->disk($disk);
        if (! empty($item['directory'])) {
            $directoryBase = $item['directory'];

            $field->directory(function ($record) use ($directoryBase) {
                return $record ? "{$directoryBase}/{$record->id}" : $directoryBase;
            });
        }
        if (isset($item['save_full_url']) && $item['save_full_url'] === true) {

            $field->dehydrateStateUsing(function ($state) use ($disk) {
                if (blank($state)) {
                    return null;
                }
                if (filter_var($state, FILTER_VALIDATE_URL)) {
                    return $state;
                }
                $localPath = storage_path('app/livewire-tmp/'.basename($state));
                if (file_exists($localPath)) {
                    try {
                        $handle = fopen($localPath, 'rb');
                        Storage::disk($disk)->put($state, $handle !== false ? $handle : '');
                        if (is_resource($handle)) {
                            fclose($handle);
                        }
                    } finally {
                        if (is_file($localPath)) {
                            @unlink($localPath);
                        }
                    }
                }

                return Storage::disk($disk)->url($state);
            });

            // 3. UI Path Formatting
            $field->formatStateUsing(function ($state) use ($disk) {
                if (blank($state)) {
                    return $state;
                }
                if (! filter_var($state, FILTER_VALIDATE_URL)) {
                    return $state;
                }

                $baseUrl = rtrim(config("filesystems.disks.{$disk}.url", ''), '/');
                $key = str_starts_with($state, $baseUrl)
                    ? ltrim(str_replace($baseUrl, '', $state), '/')
                    : ltrim(parse_url($state, PHP_URL_PATH), '/');

                $root = trim(config("filesystems.disks.{$disk}.root", ''), '/');
                if ($root && str_starts_with($key, $root.'/')) {
                    $key = substr($key, strlen($root) + 1);
                }

                return $key; // stock_images/01KH.png
            });
        
        }
        if (! empty($item['legacy_url_passthrough'])) {
            $visibility = $item['visibility'] ?? 'public';

            // Bypass the default exists() check in afterStateHydrated so
            // legacy absolute URLs (pointing at the old bucket) aren't
            // stripped from state before getUploadedFileUsing runs.
            // a martandedit image change

           
        }
        if (! empty($item['visibility'])) {
            $field->visibility($item['visibility']);
        }

        // For local/public disks with no special URL handling, give FilePond
        // a concrete {name, size, url} so it doesn't hang on "Waiting for size".
        $isLocalDisk = in_array(config("filesystems.disks.{$disk}.driver"), ['local'], true);
        $hasSaveFullUrl = isset($item['save_full_url']) && $item['save_full_url'] === true;
        $hasLegacyPassthrough = ! empty($item['legacy_url_passthrough']);

        if ($isLocalDisk && ! $hasSaveFullUrl && ! $hasLegacyPassthrough) {
            // For local/public disk: verify the stored path still exists on the disk before
            // hydrating state. Without this Filament may hang on "Waiting for size" or
            // show a broken preview when the file path does not resolve correctly.
            $field->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) use ($disk) {
                if (blank($state)) {
                    return;
                }
                $paths = is_array($state) ? array_values(array_filter((array) $state)) : [$state];
                $valid = array_values(array_filter($paths, fn ($p) => filled($p) && Storage::disk($disk)->exists($p)));
                $component->state(is_array($state) ? $valid : ($valid[0] ?? null));
            });
        }

        if (isset($item['image_editor']) && $item['image_editor'] === true) {
            $field->imageEditor(boolval($item['image_editor']));
        }
        if (! empty($item['image_editor_mode'])) {
            $mode = (int) $item['image_editor_mode'];
            // mode should be 1, 2 or 3
            if (! in_array($mode, [1, 2, 3])) {
                $mode = 3;
            }
            $field->imageEditorMode($mode);
        }
        if (! empty($item['image_preview_height'])) {
            $field->imagePreviewHeight((int) $item['image_preview_height']);
        }

        if (! empty($item['image']) && $item['image'] === true) {
            $field->image();
        }
        if (! empty($item['image_resize_mode'])) {
            $field->automaticallyResizeImagesMode($item['image_resize_mode']);
        }
        if (! empty($item['image_crop_aspect_ratio'])) {
            $field->imageEditorAspectRatioOptions($item['image_crop_aspect_ratio']);
        }
        if (! empty($item['image_resize_target_width'])) {
            $field->automaticallyResizeImagesToWidth($item['image_resize_target_width']);
        }
        if (! empty($item['image_resize_target_height'])) {
            $field->automaticallyResizeImagesToHeight($item['image_resize_target_height']);
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    // protected static function normalizeConditions(array $config): array
    // {
    //     // If it looks like a single condition (has 'field'), wrap in an array
    //     if (isset($config['field'])) {
    //         return [$config];
    //     }

    //     // Already a list of conditions
    //     return $config;
    // }
    protected static function normalizeConditions(array $config): array
    {
       // Single condition
        if (isset($config['field'])) {
          return [
            'logic' => 'and',
            'conditions' => [$config],
          ];
        }

        // New structure with logic
        if (isset($config['conditions'])) {
            return [
                'logic' => strtolower($config['logic'] ?? 'and'),
                'conditions' => $config['conditions'],
            ];

        }
        // Old array format
        return [
            'logic' => 'and',
            'conditions' => $config,
        ];
    }

    protected static function evaluateConditions(Get $get, array  $config): bool
    {
        $logic = strtolower($config['logic'] ?? 'and');
        $conditions = $config['conditions'] ?? [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value'] ?? null;

            if (! $field) {
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
                'not_in' => is_array($expected) ? ! in_array($actual, $expected, true) : false,
                'is_null' => $actual === null || $actual === '',
                'not_null' => ! ($actual === null || $actual === ''),
                default => true,
            };

           if ($logic === 'and' && ! $result) {
                 return false;
            }
             if ($logic === 'or' && $result) {
                return true;
            }
        }

         return $logic === 'and';
    }

    /* ========== Common option appliers ========== */

    protected static function applyCommonComponentOptions(Components\Component $component, array $item): Components\Component
    {
        if (! empty($item['columnSpan'])) {
            $component->columnSpan($item['columnSpan']);
        }

        // Consolidated visibility logic
        $component->visible(function (Get $get, $record, ?string $context = null, $component = null) use ($item) {
            $operation = $context ?? ($component ? $component->getContainer()->getOperation() : null);
            if (! empty($item['hidden'])) {
                return false;
            }

            if (isset($item['visible_on'])) {
                $visibleOn = (array) $item['visible_on'];
                if (! in_array($operation, $visibleOn)) {
                    return false;
                }
            }
            if (isset($item['hidden_on'])) {
                $hiddenOn = (array) $item['hidden_on'];
                if (in_array($operation, $hiddenOn)) {
                    return false;
                }
            }

            if (isset($item['visible_when'])) {
                $conditions = self::normalizeConditions($item['visible_when']);
                if (! self::evaluateConditions($get, $conditions)) {
                    return false;
                }
            }

            if (isset($item['hidden_when'])) {
                $conditions = self::normalizeConditions($item['hidden_when']);
                if (self::evaluateConditions($get, $conditions)) {
                    return false;
                }
            }

            if (isset($item['visible_if_record'])) {
                $criteria = $item['visible_if_record'];
                if (! $record || ($record->{$criteria['field']} ?? null) !== $criteria['value']) {
                    return false;
                }
            }

            if (isset($item['hidden_if_record'])) {
                $criteria = $item['hidden_if_record'];
                if ($record && ($record->{$criteria['field']} ?? null) === $criteria['value']) {
                    return false;
                }
            }

            if (! empty($item['visible_roles'])) {
                $roles = (array) $item['visible_roles'];
                $user = Auth::user();
                /** @var User $user */
                if (! $user || ! $user->hasAnyRole($roles)) {
                    return false;
                }
            }

            if (! empty($item['hidden_roles'])) {
                $roles = (array) $item['hidden_roles'];
                $user = Auth::user();
                /** @var User $user */
                if ($user && $user->hasAnyRole($roles)) {
                    return false;
                }
            }

            return true;
        });

        /** Conditional disabled based on other fields **/
        if (isset($item['disabled_when'])) {
            $conditions = self::normalizeConditions($item['disabled_when']);

            if (method_exists($component, 'disabled')) {
                $component->disabled(function (Get $get) use ($conditions) {
                    return self::evaluateConditions($get, $conditions);
                });
            }

            // Make field optional when disabled
            if (method_exists($component, 'required')) {
                if (! empty($item['required'])) {
                    $component->required(function (Get $get) use ($conditions) {
                        return ! self::evaluateConditions($get, $conditions);
                    });
                }
            }
        }

        /** Conditional required based on other fields **/
        if (isset($item['when_required'])) {
            $conditions = self::normalizeConditions($item['when_required']);
            // Make field optional when disabled
            if (method_exists($component, 'required')) {
                $component->required(function (Get $get) use ($conditions) {
                    return self::evaluateConditions($get, $conditions);
                });
            }
        }

        if (isset($item['dehydrated']) && method_exists($component, 'dehydrated')) {
            $component->dehydrated($item['dehydrated']);
        }

        return $component;
    }

    protected static function applyCommonFieldOptions(Components\Component $field, array $item): Components\Component
    {
        if (! empty($item['required'])) {
            if ($item['required'] === 'create' || $item['required'] === 'edit') {
                $field->required(fn (string $context): bool => $context === $item['required']);
            } else {
                $field->required();
            }
        }
        if (! empty($item['disabled'])) {
            $field->disabled();
        }
        if (! empty($item['hidden'])) {
            $field->hidden();
        }

        if (array_key_exists('default', $item)) {
            $field->default($item['default']);
        }

        // if (!empty($item['placeholder']) && method_exists($field, 'placeholder')) {
        //     $field->placeholder($item['placeholder']);
        // }

        if (! empty($item['placeholder']) && method_exists($field, 'placeholder')) {
            $placeholder = $item['placeholder'];

            // If the placeholder string ends with an image extension, wrap it in HTML.
            // FilePond only renders labelIdle when no file is loaded, so we always
            // return the stencil — that way it reappears after the user removes a file.
            if (preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $placeholder)) {
                $imageUrl = asset(str_replace('public/', '', $placeholder));
                $field->placeholder(new HtmlString(
                    "<div><img src='{$imageUrl}' class='stencil-img' alt='Upload stencil' /></div>"
                ));
            } else {
                $field->placeholder($placeholder);
            }
        }
        if (array_key_exists('extra_attributes', $item)) {
            $field->extraAttributes($item['extra_attributes']);
        }
        if (! empty($item['helperText'])) {
            $field->helperText($item['helperText']);
        }

        if (! empty($item['hint']) && method_exists($field, 'hint')) {
            $field->hint($item['hint']);
        }

        if (! empty($item['hintIcon']) && method_exists($field, 'hintIcon')) {
            $field->hintIcon('heroicon-o-information-circle')
                  ->hintIconTooltip($item['hintIcon']);
        }

        if (! empty($item['columnSpan'])) {
            $field->columnSpan($item['columnSpan']);
        }

        if (! empty($item['reactive']) && method_exists($field, 'reactive')) {
            $field->reactive();
        }
        if (! empty($item['live']) && method_exists($field, 'live')) {
            $field->live();
        }

        if (! empty($item['debounce']) && method_exists($field, 'debounce')) {
            $field->debounce($item['debounce']);
        }

        if (array_key_exists('dehydrated', $item)) {
            $field->dehydrated($item['dehydrated']);
        }

        if (! empty($item['uppercase'])) {
            $field->dehydrateStateUsing(fn ($state) => strtoupper((string) $state));
        }

        // Consolidated visibility logic for fields
        $field->visible(function (Get $get, $record, ?string $context = null, $component = null) use ($item) {
            $operation = $context ?? ($component ? $component->getContainer()->getOperation() : null);
            if (! empty($item['hidden'])) {
                return false;
            }

            if (isset($item['visible_on'])) {
                $visibleOn = (array) $item['visible_on'];
                if (! in_array($operation, $visibleOn)) {
                    return false;
                }
            }
            if (isset($item['hidden_on'])) {
                $hiddenOn = (array) $item['hidden_on'];
                if (in_array($operation, $hiddenOn)) {
                    return false;
                }
            }

            if (isset($item['visible_when'])) {
                $conditions = self::normalizeConditions($item['visible_when']);
                if (! self::evaluateConditions($get, $conditions)) {
                    return false;
                }
            }

            if (isset($item['hidden_when'])) {
                $conditions = self::normalizeConditions($item['hidden_when']);
                if (self::evaluateConditions($get, $conditions)) {
                    return false;
                }
            }

            if (isset($item['visible_if_record'])) {
                $criteria = $item['visible_if_record'];
                if (! $record || ($record->{$criteria['field']} ?? null) !== $criteria['value']) {
                    return false;
                }
            }

            if (isset($item['hidden_if_record'])) {
                $criteria = $item['hidden_if_record'];
                if ($criteria['value'] === 'auth_id') {
                    $criteria['value'] = auth()->id();
                }
                if ($record && ($record->{$criteria['field']} ?? null) === $criteria['value']) {
                    return false;
                }
            }

            if (! empty($item['visible_roles'])) {
                $roles = (array) $item['visible_roles'];
                $user = Auth::user();
                /** @var User $user */
                if (! $user || ! $user->hasAnyRole($roles)) {
                    return false;
                }
            }

            if (! empty($item['hidden_roles'])) {
                $roles = (array) $item['hidden_roles'];
                $user = Auth::user();
                /** @var User $user */
                if ($user && $user->hasAnyRole($roles)) {
                    return false;
                }
            }

            return true;
        });

        if (! empty($item['disabled_mode'])) {
            $disabledMode = $item['disabled_mode'];

            $field->disabled(function ($livewire, $component) use ($disabledMode) {
                // Try to get operation from component container first (works in Modals)
                $operation = $component->getContainer()->getOperation();

                // Fallback to Livewire instance check
                if (! $operation) {
                    if ($livewire instanceof CreateRecord) {
                        $operation = 'create';
                    } elseif ($livewire instanceof EditRecord) {
                        $operation = 'edit';
                    }
                }

                $isCreate = $operation === 'create';
                $isEdit = $operation === 'edit';

                return ($disabledMode === 'create' && $isCreate) || ($disabledMode === 'edit' && $isEdit) || ($disabledMode === 'both');
            });
        }

        /** Conditional disabled based on other fields **/
        if (isset($item['disabled_when'])) {
            $conditions = self::normalizeConditions($item['disabled_when']);

            $field->disabled(function (Get $get) use ($conditions) {
                return self::evaluateConditions($get, $conditions);
            });

            if (! empty($item['required'])) {
                // Make field optional when disabled
                $field->required(function (Get $get) use ($conditions) {
                    return ! self::evaluateConditions($get, $conditions);
                });
            }
        }
        /** Disable field based on related record DB value (not form state) **/
        if (isset($item['disabled_if_record'])) {
            $criteriaList = isset($item['disabled_if_record']['field'])
                ? [$item['disabled_if_record']]
                : $item['disabled_if_record'];

            $evaluateDisabledIfRecord = function ($record) use ($criteriaList): bool {
                if (! $record) {
                    return false;
                }

                foreach ($criteriaList as $criteria) {
                    $conditionField = $criteria['field'] ?? null;
                    $conditionValue = $criteria['value'] ?? null;
                    $conditionOperator = $criteria['operator'] ?? '=';

                    $related = ! empty($criteria['relation']) ? $record->{$criteria['relation']} : $record;
                    if (! $related) {
                        return false;
                    }

                    $actual = $related->{$conditionField} ?? null;

                    if (! self::matchCondition($actual, $conditionOperator, $conditionValue)) {
                        return false;
                    }
                }

                return true;
            };

            $field->disabled(fn ($record) => $evaluateDisabledIfRecord($record));
            if ($item['required'] ?? false) {
                $field->required(fn ($record) => ! $evaluateDisabledIfRecord($record));
            }
        }

        /** Conditional required based on other fields **/
        if (isset($item['when_required'])) {
            $conditions = self::normalizeConditions($item['when_required']);
            // Make field optional when disabled
            $field->required(function (Get $get) use ($conditions) {
                return self::evaluateConditions($get, $conditions);
            });
        }

        if (isset($item['required_with'])) {
            $field->requiredWith($item['required_with']);
        }
        // required_unless
        if (isset($item['required_unless'])) {
            $fieldName = $item['required_unless']['field'];
            $valueColumn = $item['required_unless']['value'];
            $field->required(fn ($get) => $get($fieldName) !== $valueColumn);
            $field->validationMessages([
                'required' => $item['label'].' is Required',
            ]);
        }

        // live_on_blur
        if ($item['live_on_blur'] ?? false) {
            $field->live(onBlur: true);
        }

        // validate_on_change: validate only this field immediately on every state change
        if ($item['validate_on_change'] ?? false) {
            $field->live()->afterStateUpdated(function ($livewire, $component) {
                $livewire->validateOnly($component->getStatePath());
            });
        }

        // validate_on_blur: live on blur + optional uppercase + trigger field-level validation
        if ($item['validate_on_blur'] ?? false) {
            $isUppercase = ! empty($item['uppercase']);
            $field->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set, $livewire, $component) use ($isUppercase) {
                    if ($isUppercase) {
                        $set($component->getStatePath(), strtoupper((string) $state));
                    }
                    $livewire->validateOnly($component->getStatePath());
                });
        }

        // Check for your custom 'after_state_updated' key
        if (isset($item['after_state_updated'])) {
            $callbackString = $item['after_state_updated'];

            $field->afterStateUpdated(function ($state, $component, $get, $set) use ($callbackString) {
                // dd($state, $component, $get, $set);
                $availableParams = [
                    'state' => $state,
                    'get' => $get,
                    'set' => $set,
                    'component' => $component,
                    'container' => $component->getContainer(),
                    'record' => $component->getRecord(),
                    'model' => $component->getModel(),
                    'livewire' => $component->getLivewire(),
                    'operation' => $component->getContainer()->getOperation(),
                    'statePath' => $component->getStatePath(),
                ];
                app()->call($callbackString, $availableParams);
            });
        }

        if (isset($item['custom_after_state_updated'])) {
            $callback = $item['custom_after_state_updated'];
            // Sibling field names (relative to this field) that share the same check,
            // so a stale error left on one of them gets cleared once this one passes.
            $relatedFields = $item['custom_after_state_updated_related'] ?? [];

            $field->afterStateUpdated(function ($state, Set $set, Get $get, $livewire, $record, $component) use ($callback, $relatedFields) {
                $statePath = $component->getStatePath();
                $basePath = Str::contains($statePath, '.') ? Str::beforeLast($statePath, '.') : null;

                $relatedPaths = array_map(
                    fn ($name) => $basePath ? "{$basePath}.{$name}" : $name,
                    $relatedFields
                );

                $errorStatus = app()->call($callback, [
                    'state' => $state,
                    'set' => $set,
                    'get' => $get,
                    'livewire' => $livewire,
                    'record' => $record,
                    'component' => $component,
                ]);

                if (! empty($errorStatus) && ! empty($errorStatus['status'])) {
                    throw ValidationException::withMessages([
                        $statePath => $errorStatus['error'],
                    ]);
                }

                // Passed: clear any stale error left on this field or its siblings
                // by a previous run of this same check.
                $livewire->resetErrorBag([$statePath, ...$relatedPaths]);
            });
        }
        // custom_rule
        if (isset($item['custom_rule'])) {
            $callbackString = $item['custom_rule'];
            $field->rule(fn ($get, $record) => function (string $attribute, $value, $fail) use ($callbackString, $record, $get) {
                app()->call($callbackString, [
                    'attribute' => $attribute,
                    'value' => $value,
                    'fail' => $fail,
                    'record' => $record,
                    'get' => $get,
                ]);
            });
        }
        if (isset($item['custom_rule_with_dipendency'])) {
            $callbackString = $item['custom_rule_with_dipendency'];
            $isDepend = $item['dependency_enabled'] ?? false;
            $dependField = $item['dependency_field'] ?? null;

            $field->rule(fn ($get, $record) => function (string $attribute, $value, $fail) use ($callbackString, $record, $get, $isDepend, $dependField) {
                app()->call($callbackString, [
                    'attribute' => $attribute,
                    'value' => $value,
                    'fail' => $fail,
                    'record' => $record,
                    'get' => $get,
                    'isdepend' => $isDepend,
                    'depenfield' => $dependField,
                ]);
            });
        }

        /** Handle "hidden_if_record" logic **/
        if (isset($item['hidden_if_record'])) {
            $criteria = $item['hidden_if_record']; // e.g., ['field' => 'status', 'value' => 'sold']

            $field->hidden(function ($record) use ($criteria) {
                // Safety check: Create pages have no record
                if (! $record) {
                    return false;
                }

                $dbValue = $record->{$criteria['field']} ?? null;

                return $dbValue === $criteria['value'];
            });
        }
        /** Handle "visible_if_record" logic **/
        if (isset($item['visible_if_record'])) {
            $criteria = $item['visible_if_record']; // e.g., ['field' => 'status', 'value' => 'sold']

            $field->visible(function ($record) use ($criteria) {
                // Safety check: Create pages have no record
                if (! $record) {
                    return false;
                }

                $dbValue = $record->{$criteria['field']} ?? null;

                return $dbValue === $criteria['value'];
            });
        }
        if (isset($item['clean_inactive_user'])) {
            $field->afterStateHydrated(function ($state, $set) use ($item) {
                if (
                    $state && User::groupView()->where('id', $state)
                        ->where('status', '1')
                        ->doesntExist()
                ) {
                    $set($item['clean_inactive_user'], null);
                }
            });
        }

        if (isset($item['clean_city_id']) && is_array($item['clean_city_id'])) {
            $field->afterStateHydrated(function ($state, $set, $get) use ($item) {
                if ($state) {
                    $stateId = $get($item['clean_city_id'][0]);
                    $cities = CommonHelper::citiesByStateId($stateId);
                    if (! array_key_exists($state, $cities)) {
                        $cityId = $item['clean_city_id'][1];
                        $set($cityId, null);
                    }
                }
            });
        }
        
        return $field;
    }

    protected static function buildColorPicker(array $item): Forms\Components\ColorPicker
    {
        $field = Forms\Components\ColorPicker::make($item['name'])
            ->label($item['label'] ?? null);

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildTagsInput(array $item): Forms\Components\TagsInput
    {
        $field = Forms\Components\TagsInput::make($item['name'])
            ->label($item['label'] ?? null);

        if (! empty($item['suggestions'])) {
            $field->suggestions($item['suggestions']);
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildTimePicker(array $item): Forms\Components\TimePicker
    {
        $field = Forms\Components\TimePicker::make($item['name'])
            ->label($item['label'] ?? null)
            ->native($item['native'] ?? false);

        return self::applyCommonFieldOptions($field, $item);
    }

    /**
     * Populate a Select field with records from a related Studio module.
     * The relate_module key must match the module's fullname (e.g. 'crm_contacts').
     * display_field is the column whose value is shown as the option label.
     * The field stores the related record's primary key as a plain string.
     */
    protected static function applyRelateOptions(Forms\Components\Select $field, array $item): void
    {
        $relateModule = $item['relate_module'] ?? null;
        $configured   = $item['display_field'] ?? 'name';

        if (! $relateModule) {
            return;
        }

        $modelClass = 'App\\Models\\' . Str::studly($relateModule);
        if (! class_exists($modelClass)) {
            $modelClass = 'App\\Models\\' . Str::studly(Str::singular($relateModule));
        }

        if (! class_exists($modelClass)) {
            $field->options([]);
            return;
        }

        $displayField = self::resolveRelateDisplayColumn($modelClass, $configured);

        $field
            ->searchable()
            ->preload()
            ->getSearchResultsUsing(function (string $search) use ($modelClass, $displayField) {
                if ($displayField === '__full_name__') {
                    $query = $modelClass::query()
                        ->select(['id', 'first_name', 'last_name'])
                        ->orderBy('first_name')
                        ->limit(50);
                    if ($search !== '') {
                        $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }
                    return $query->get()
                        ->mapWithKeys(fn ($r) => [
                            $r->id => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''))
                        ])
                        ->toArray();
                }

                $query = $modelClass::query()
                    ->orderBy($displayField)
                    ->limit(50);
                if ($search !== '') {
                    $query->where($displayField, 'like', "%{$search}%");
                }
                return $query->pluck($displayField, 'id')
                    ->map(fn ($label) => (string) ($label ?? ''))
                    ->toArray();
            })
            ->getOptionLabelUsing(function ($value) use ($modelClass, $displayField) {
                $record = $modelClass::find($value);
                if (! $record) {
                    return $value;
                }
                if ($displayField === '__full_name__') {
                    return trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: $value;
                }
                return $record->{$displayField} ?? $value;
            });
    }

    /**
     * Find the best column to use as the display label for a relate dropdown.
     * Tries the user-configured column first, then falls back through common
     * naming conventions. Returns '__full_name__' when first_name+last_name exist.
     */
    public static function resolveRelateDisplayColumn(string $modelClass, string $configured): string
    {
        $instance = new $modelClass;
        $table    = $instance->getTable();

        // Configured column takes priority if it actually exists
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, $configured)) {
            return $configured;
        }

        // Common single-column labels
        foreach (['name', 'title', 'label', 'full_name', 'display_name'] as $col) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        // Combined first + last name
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'first_name')
            && \Illuminate\Support\Facades\Schema::hasColumn($table, 'last_name')) {
            return '__full_name__';
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'first_name')) {
            return 'first_name';
        }

        // Last resort: primary key (IDs) — at least won't throw
        return $instance->getKeyName();
    }

    /*protected static function applyPopulationOptions(Components\Component $field, array $item): void
    {
        if (isset($item['populate_fields'], $item['helper_class']) && (isset($item['populate_method']) || isset($item['helper_method']))) {
            $helperClass = $item['helper_class'];
            $helperMethod = $item['populate_method'] ?? $item['helper_method'];

            // devfatal($helperMethod);
            $populateFields = $item['populate_fields'];
            $populateMode = $item['populate_mode'] ?? 'both';

            $field->live()->afterStateUpdated(function ($state, Set $set, $livewire, $component) use ($helperClass, $helperMethod, $populateFields, $populateMode) {
                // Determine page type / operation
                $operation = $component->getContainer()->getOperation();

                if (!$operation) {
                    if ($livewire instanceof CreateRecord)
                        $operation = 'create';
                    elseif ($livewire instanceof EditRecord)
                        $operation = 'edit';
                }

                $isCreate = $operation === 'create';
                $isEdit = $operation === 'edit';

                // Mode gate
                $shouldRun = ($populateMode === 'create' && $isCreate) || ($populateMode === 'edit' && $isEdit) || ($populateMode === 'both');

                if (!$shouldRun) {
                    return;
                }

                if (!class_exists($helperClass) || !method_exists($helperClass, $helperMethod)) {
                    return;
                }

                $data = $helperClass::$helperMethod($state);

                // Populate or clear fields
                foreach ($populateFields as $targetField) {
                    $set(
                        $targetField,
                        $data[$targetField] ?? null
                    );
                }
            });
        }
    }*/

    protected static function applyPopulationOptions(Components\Component $field, array $item): void
    {

        if (! isset($item['populate_fields'], $item['helper_class']) || (! isset($item['populate_method']) && ! isset($item['helper_method']))) {
            return;
        }

        $helperClass = $item['helper_class'];
        $helperMethod = $item['populate_method'] ?? $item['helper_method'];
        $populateFields = $item['populate_fields'];
        $populateMode = $item['populate_mode'] ?? 'both';
        $helperParams = $item['helper_params'] ?? [];
        $helperType = $item['helper_type'] ?? 'static';
        $populateParams = $item['populate_params'] ?? null;

        if (! class_exists($helperClass) || ! method_exists($helperClass, $helperMethod)) {
            return;
        }

        $field->live()->afterStateUpdated(function ($state, Set $set, Get $get, $livewire, $component) use ($helperClass, $helperMethod, $populateFields, $populateMode, $helperParams, $helperType, $populateParams) {
            if ($state === null || $state === '') {

                  foreach ($populateFields as $targetField) {
                    $set($targetField, null);
                }
                return;
            }

            // Detect operation
            $operation = $component->getContainer()->getOperation();

            if (! $operation) {
                if ($livewire instanceof CreateRecord) {
                    $operation = 'create';
                } elseif ($livewire instanceof EditRecord) {
                    $operation = 'edit';
                }
            }

            $isCreate = $operation === 'create';
            $isEdit = $operation === 'edit';

            $shouldRun = ($populateMode === 'create' && $isCreate) || ($populateMode === 'edit' && $isEdit) || ($populateMode === 'both');

            if (! $shouldRun) {
                return;
            }

            // Build helper arguments
            $args = [];

            if ($populateParams !== null) {
                foreach ($populateParams as $param) {
                    $args[] = ($param === '$state') ? $state : $get($param);
                }
            } elseif (! empty($helperParams)) {
                foreach ($helperParams as $param) {

                    if ($helperType === 'hybrid') {
                        $value = (is_string($param) && str_starts_with($param, '@')) ? $get(substr($param, 1)) : $param;
                    } else {
                        $value = $get($param);
                    }

                    $args[] = $value;
                }
            } else {
                $args[] = $state;
            }

            $data = $helperClass::$helperMethod(...$args);

            if (! is_array($data)) {
                return;
            }

            foreach ($populateFields as $targetField) {
                $set($targetField, $data[$targetField] ?? null);
            }
        });
    }
}
