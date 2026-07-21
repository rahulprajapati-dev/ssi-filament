# SSI-Filament — Agent Guidelines

## Project Overview

SSI-Filament is a **JSON-driven low-code Studio Builder** built on Laravel 13 + Filament 5.
Developers configure modules (data entities) through an admin UI; the Studio generates Eloquent
models, Filament resources, migrations, and layout JSON automatically. Custom code lives in
developer-owned files that the Studio never overwrites.

---

## Stack & Versions

| Package | Version |
|---|---|
| PHP | 8.4 |
| laravel/framework | v13 |
| filament/filament | v5 |
| livewire/livewire | v4 |
| tailwindcss | v4 |

---

## Core Architecture

### Studio Models (source of truth)
- `App\Models\Module` — one row per module definition. Use `$module->computed_table` (never `$module->table` — that collides with an Eloquent internal method).
- `App\Models\ModuleField` — field definitions. System fields have `sort_order >= 9990`.
- `App\Models\ModuleLayout` — layout JSON per view type (create / edit / detail / list).

### Studio Generators (`app/Helpers/Studio/`)
| Class | Responsibility |
|---|---|
| `StudioManager` | Orchestrates deploy / rebuild / uninstall. Entry point for all Studio operations. |
| `ModelGenerator` | Writes `Base{Model}.php` and the developer-owned `{Model}.php`. |
| `MigrationGenerator` | Generates migration files from field definitions. |
| `ResourceGenerator` | Writes the Filament Resource, pages, and glue files. |
| `LayoutGenerator` | Generates form/table JSON from field + layout records. |
| `SchemaSyncService` | Syncs DB schema directly (Pass 1 = add/rename, Pass 2 = type-change detection). |
| `ModuleValidator` | Pre-deploy checks (name conflicts, system field collisions, etc.). |
| `DropdownHandler` | All dropdown DOM read/write operations. Single source of truth for dropdowns. |
| `StubRenderer` | Fills `{{PLACEHOLDER}}` tokens in `.stub` template files. |
| `FieldTypeMap` | Central registry mapping field type strings → DB types / Filament components. |

### JSON Renderers (`app/Helpers/`)
- `JsonFormBuilder` — converts form/schema JSON into Filament form components.
- `JsonTableBuilder` — converts table JSON into Filament table columns, filters, and actions.

---

## Key Conventions

### Never use `$module->table`
The `table` accessor was renamed to `computedTable` (Eloquent Attribute) to avoid a collision
with `Model::getTable()`. Always use:
```php
$module->computed_table   // Attribute access
```

### Filament 5 property type covariance
Filament 5 enforces strict property type covariance. Never redeclare `$navigationIcon` or
`$navigationGroup` as properties in page/resource subclasses — use method overrides instead:
```php
public function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-...'; }
public function getNavigationGroup(): ?string { return 'Studio'; }
```

### Two-tier model system
Studio generates two model files per module:
- `app/Models/Studio/Base{Model}.php` — Studio-owned, regenerated on every Repair & Rebuild.
- `app/Models/{Model}.php` — Developer-owned, generated once, never overwritten.

Always extend the base class; never redeclare `$table`, `$guarded`, or Studio relationships.

### Custom override directories
Developers place JSON overrides in:
- `app/Filament/Resources/{Resource}/CustomSchemas/` — form overrides (create/edit/detail/default)
- `app/Filament/Resources/{Resource}/CustomTables/` — table override (listView.json)

These directories are never touched by any Studio operation.

### Hook system in JSON
JSON configs wire PHP methods using `@` notation:
```json
"hooks": { "action": "\\App\\Filament\\Resources\\Modules\\Hooks\\ModuleHooks@deployModule" }
```
Hook classes receive `($record, $data)` and return `['success' => bool]`.

### System fields
Fields with `sort_order >= 9990` are Studio-seeded system fields (`created_at`, `updated_at`,
`created_by`, `updated_by`). The validator excludes them from name-conflict checks.

---

## Dropdown DOMs

Two separate sources — `DropdownHandler::get()` merges both transparently:

| File | Owner | Purpose |
|---|---|---|
| `config/studio_doms.php` | Studio | Built-in option groups (`field_type_dom`, `layout_type_dom`, `moudle_icons_dom`, `visibility_mode_dom`, `condition_logic_dom`, `relationship_type_dom`, `filter_type_dom`, `operator_dom`). Edit the PHP file directly. |
| `storage/app/SSI/Dropdowns/app_doms.json` | App / Developer | User-created option groups (`status_dom`, `gender_dom`, module-generated DOMs). Written at runtime by `DropdownHandler`. |

Always use `DropdownHandler` — `DropdownService` has been removed.

---

## Studio Admin Modules (Filament Resources)

All three Studio admin resources follow the same JSON-driven pattern:

| Resource | Path | List JSON |
|---|---|---|
| Modules | `app/Filament/Resources/Modules/` | `Tables/listView.json` |
| Field Builder | `app/Filament/Resources/ModuleFields/` | `Tables/listView.json` |
| Layout Builder | `app/Filament/Resources/ModuleLayouts/` | `Tables/listView.json` |

---

## Layout Inheritance Direction

When a Create View layout is saved with `inherit_edit_layout` or `inherit_detail_layout` toggled on, the Create View `layout_json` is **pushed into** the Edit / Detail layout records. Direction is always: **Create → Edit / Detail** (never reverse).

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan studio:deploy {module}` | Deploy a module (generates all files + schema) |
| `php artisan studio:rebuild {module}` | Repair & Rebuild an existing module |
| `php artisan studio:list [--deployed] [--pending]` | List all modules and their status |
| `php artisan schema:generate {table} {Resource}` | Generate a JSON schema from an existing DB table |

---

## Coding Rules

- Follow all existing code conventions. Check sibling files before creating new ones.
- Use descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Default to **no comments**. Only comment when the WHY is non-obvious (hidden constraint, workaround, subtle invariant).
- Always use `FieldTypeMap` when mapping a field type to a DB type or Filament component — never hard-code type strings elsewhere.
- All write operations to the DB schema go through `SchemaSyncService` — never raw `Schema::` calls outside it.
- `StudioManager` is the only entry point for deploy / rebuild / uninstall — never call generators directly from UI hooks.
- Use `DB::transaction()` around bulk insert operations (e.g. `seedSystemFields()`).

## PHP Rules

- PHP 8.4 — use constructor property promotion, named arguments, match expressions.
- Explicit return types and parameter type hints on all methods.
- Always use curly braces for control structures, even single-line bodies.
- `declare(strict_types=1)` on all new PHP files.

## Do Not

- Rename `computed_table` back to `table` on `Module`.
- Add `DropdownService` back — it has been deleted; use `DropdownHandler` directly.
- Write Studio DOM keys (`field_type_dom`, etc.) to `app_doms.json` programmatically — check with `DropdownHandler::isStudioDom($key)` first.
- Redeclare Filament navigation properties as class properties in subclasses — use method overrides.
- Call generators (`ModelGenerator`, `ResourceGenerator`, etc.) directly from hooks or controllers — always go through `StudioManager`.
- Create new base folders or change package dependencies without approval.
