<?php

namespace App\Helpers;

use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\Form;
use App\Helpers\Studio\DropdownHandler;

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
            'textarea' => self::buildTextarea($item),
            'select' => self::buildSelect($item),
            'toggle' => self::buildToggle($item),
            'checkbox' => self::buildCheckbox($item),
            'datePicker' => self::buildDatePicker($item),
            'dateTimePicker' => self::buildDateTimePicker($item),
            'fileUpload' => self::buildFileUpload($item),
            'radio' => self::buildRadio($item),
            'checkboxList' => self::buildCheckboxList($item),
            'placeholder' => self::buildPlaceholder($item),
            'view' => self::buildView($item),
            'dragDrop' => self::buildDragDrop($item),

            default => null,
        };
    }

    /* =============== Hooks ================== */
    protected static function resolveHook($methodHook): ?callable
    {
        if (! $methodHook || ! str_contains($methodHook, '@')) {
            return null;
        }

        // We use a closure that requests dependencies via Injection.
        // This ensures Filament passes us the Livewire component, Action, and Record/Data if available.
        return function (Component $livewire, Action $action, $record = null, $data = null, $form = null // mountUsing provides form
        ) use ($methodHook) {

            // Resolve Resource class from Livewire component
            $resourceClass = null;
            if (method_exists($livewire, 'getResource')) {
                $resourceClass = $livewire->getResource(); // Using instance or static call depending on component
            }
            // Fallback for static getResource on page classes
            if (! $resourceClass && method_exists($livewire, 'getResource')) { // try static
                try {
                    $resourceClass = $livewire::getResource();
                } catch (\Throwable $t) {
                }
            }

            [$class, $method] = explode('@', $methodHook);

            // Determine target to call
            $targetClass = $class;
            $useStatic = false;
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
                        if (! $resourceClass) {
                            // Try to execute on the livewire component itself if acceptable?
                            // But error message should be clear.
                            throw new \RuntimeException("Cannot execute trait hook [{$methodHook}]. Resource context not found.");
                        }
                        throw new \RuntimeException("Method [{$method}] not found on Resource [{$resourceClass}].");
                    }
                }
            }

            // Check if calling static
            if (! $useStatic) {
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
                return (new $targetClass)->{$method}(...$callArgs);
            }

            // Otherwise use container
            return app($targetClass)->{$method}(...$callArgs);
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
            ->schema(self::buildComponents($item['schema'] ?? []))
            ->columns($item['columns'] ?? 1);

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
            'repeaterName' => $item['repeater_name'] ?? null,
            'source' => $item['source'] ?? 'repeater',   // 'repeater' | 'module_fields'
            'dependsOn' => $item['depends_on'] ?? null,      // e.g. 'module_id'
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
            $field->formatStateUsing(function (string $state) use ($callbackString) {
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
        if (! empty($item['dropdown'])) {
            $dropdownType = $item['dropdown'];
            $field->formatStateUsing(function ($state) use ($dropdownType) {
                $options = DropdownHandler::get($dropdownType);
                return $options[$state] ?? $state;
            });
        }

        return self::applyCommonFieldOptions($field, $item);
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

        if (! empty($item['rules'])) {
            $field->rules([
                Password::min($item['rules'])
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ]);
        }

        if (($item['type'] ?? null) === 'email') {
            $field->email();
        }

        if (! empty($item['messages'])) {
            $field->validationMessages($item['messages']);
        }

        if (! empty($item['validation'])) {
            $field->rules($item['validation']);
        }

        /*
            "live": true,
            "duplicate": ["leads","mobile"],
        */

        if (! empty($item['duplicate']) && is_array($item['duplicate'])) {
            $field->rules([
                fn ($get) => Rule::unique($item['duplicate'][0], $item['duplicate'][1])->ignore($get('id')), // ignore current record on edit
            ])->reactive();
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

        if (! empty($item['options'])) {
            $field->options($item['options']);
        }

        return self::applyCommonFieldOptions($field, $item);
    }

    protected static function buildTextarea(array $item): Forms\Components\Textarea
    {
        $field = Forms\Components\Textarea::make($item['name'])
            ->label($item['label'] ?? null);

        if ($rows = $item['rows'] ?? null) {
            $field->rows($rows);
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
            'eloquent' => self::applyEloquentOptions($field, $item),
            'helper' => self::applyHelperOptions($field, $item),
            'enum' => self::applyEnumOptions($field, $item),
            'config' => self::applyConfigOptions($field, $item),
            default => null,
        };

        // Clear dependent fields on update
        if (! empty($item['clear_on_update']) && is_array($item['clear_on_update'])) {
            $targets = $item['clear_on_update'];

            $field->afterStateUpdated(function ($state, Set $set) use ($targets) {
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

        // options
        if (empty($item['options'])) {
            $field->options([]);

            return self::applyCommonFieldOptions($field, $item);
        } else {
            $field->options($item['options']);
        }
        if (! empty($item['clear_on_update']) && is_array($item['clear_on_update'])) {
            $targets = $item['clear_on_update'];

            $field->afterStateUpdated(function ($state, Set $set) use ($targets) {
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
                            return;
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

            $field->afterStateUpdated(function ($state, Set $set) use ($targets) {
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

            $field->afterStateUpdated(function ($state, Set $set) use ($targets) {
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
            $field->beforeOrEqual(fn ($record, $get) => function () use ($callbackString, $record, $get) {
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
            $value === 'now' => Carbon::today(),
            str_starts_with($value, '+'),
            str_starts_with($value, '-') => Carbon::parse($value),
            default => Carbon::parse($value),
        };
    }

    protected static function matchCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'in' => in_array($actual, (array) $expected),
            'not_in' => ! in_array($actual, (array) $expected),
            default => $actual == $expected,  // '='
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
        if (! empty($item['accepted_file_types'])) {
            if (! is_array($item['accepted_file_types'])) {
                $item['accepted_file_types'] = explode(',', $item['accepted_file_types']);
            }
            $field->acceptedFileTypes($item['accepted_file_types']);
        }
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
                    Storage::disk($disk)->put($state, file_get_contents($localPath));
                    unlink($localPath); // Cleanup local temp after move
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
            $field->getUploadedFileUsing(function ($file, $state) use ($disk) {
                $url = Storage::disk($disk)->url($state);
                if (blank($state)) {
                    return null;
                }

                // Strip full URL to relative key
                if (filter_var($state, FILTER_VALIDATE_URL)) {
                    $baseUrl = rtrim(config("filesystems.disks.{$disk}.url", ''), '/');
                    $key = str_starts_with($state, $baseUrl)
                        ? ltrim(str_replace($baseUrl, '', $state), '/')
                        : ltrim(parse_url($state, PHP_URL_PATH), '/');

                    $root = trim(config("filesystems.disks.{$disk}.root", ''), '/');
                    if ($root && str_starts_with($key, $root.'/')) {
                        $key = substr($key, strlen($root) + 1);
                    }
                } else {
                    $key = $state; // already relative key
                }

                // Return array with explicit keys FilePond expects
                return [
                    'name' => basename($key),
                    'url' => Storage::disk($disk)->url($key),
                ];
            });
        }
        if (! empty($item['legacy_url_passthrough'])) {
            $visibility = $item['visibility'] ?? 'public';

            // Bypass the default exists() check in afterStateHydrated so
            // legacy absolute URLs (pointing at the old bucket) aren't
            // stripped from state before getUploadedFileUsing runs.
            // a martandedit image change
            $field->fetchFileInformation(false);

            $field->getUploadedFileUsing(function ($file, $state) use ($disk, $visibility) {
                if (blank($state)) {
                    return null;
                }

                $guessType = static function (string $path): ?string {
                    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

                    return match ($ext) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'svg' => 'image/svg+xml',
                        'pdf' => 'application/pdf',
                        default => null,
                    };
                };

                if (filter_var($state, FILTER_VALIDATE_URL)) {
                    // Legacy bucket has no CORS headers, so FilePond's XHR fetch
                    // for image-preview thumbnails is blocked. Route through the
                    // signed in-app proxy so it's same-origin.
                    //
                    // The path filename intentionally omits the image extension —
                    // staging nginx intercepts `*.jpg/.jpeg/.png` paths as static
                    // assets and returns 404 before PHP runs. FilePond detects the
                    // mime from the proxy response Content-Type header instead.
                    $filename = basename(parse_url($state, PHP_URL_PATH) ?: $state) ?: 'image';
                    $filename = pathinfo($filename, PATHINFO_FILENAME);
                    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'image';

                    $proxied = URL::temporarySignedRoute(
                        'admin.legacy-image-proxy',
                        now()->addHours(2),
                        ['url' => $state, 'filename' => $filename],
                    );

                    return [
                        'name' => $filename,
                        'size' => 0,
                        'type' => $guessType($state),
                        'url' => $proxied,
                    ];
                }

                $storage = Storage::disk($disk);
                // try {
                $url = $visibility === 'private'
                    ? $storage->temporaryUrl($state, now()->addMinutes(5))
                    : $storage->url($state);
                /* } catch (\Throwable $e) {
                     // Local-style disks don't implement temporaryUrl().
                     $url = $storage->url($state);
                 } */

                return [
                    'name' => basename($state),
                    'size' => 0,
                    'type' => $guessType($state),
                    'url' => $url,
                ];
            });
        }
        if (! empty($item['visibility'])) {
            $field->visibility($item['visibility']);
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

    protected static function normalizeConditions(array $config): array
    {
        // If it looks like a single condition (has 'field'), wrap in an array
        if (isset($config['field'])) {
            return [$config];
        }

        // Already a list of conditions
        return $config;
    }

    protected static function evaluateConditions(Get $get, array $conditions): bool
    {
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

            if (! $result) {
                return false; // AND logic: one false breaks
            }
        }

        return true;
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

        if (! empty($item['extra_attributes'])) {
            $field->extraAttributes($item['extra_attributes']);
        }

        if (! empty($item['uppercase'])) {
            $field->dehydrateStateUsing(fn ($state) => strtoupper((string) $state));
        }

        // Handle Visibility (Roles)
        if (! empty($item['visible_roles'])) {
            $roles = is_array($item['visible_roles']) ? $item['visible_roles'] : [$item['visible_roles']];
            $field->hidden(function () use ($roles) {
                $user = auth()->user();

                return ! $user || ! $user->hasAnyRole($roles);
            });
        }

        if (! empty($item['hidden_roles'])) {
            $roles = is_array($item['hidden_roles']) ? $item['hidden_roles'] : [$item['hidden_roles']];
            $field->hidden(function () use ($roles) {
                $user = auth()->user();

                return $user && $user->hasAnyRole($roles);
            });
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
                        $set($component->getName(), strtoupper((string) $state));
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

        if (! class_exists($helperClass) || ! method_exists($helperClass, $helperMethod)) {
            return;
        }

        $field->live()->afterStateUpdated(function ($state, Set $set, Get $get, $livewire, $component) use ($helperClass, $helperMethod, $populateFields, $populateMode, $helperParams, $helperType) {

            if (blank($state)) {
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

            if (! empty($helperParams)) {
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
