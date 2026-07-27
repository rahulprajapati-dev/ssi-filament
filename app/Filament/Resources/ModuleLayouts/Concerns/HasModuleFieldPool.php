<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModuleLayouts\Concerns;

use App\Console\Commands\StudioSyncFields;
use App\Helpers\Studio\FieldTypeMap;
use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleLayout;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared behaviour for any Filament page / relation-manager that hosts
 * a layout-builder drag-drop component.
 *
 * Classes that use this trait may override resolveLayoutType() to return
 * the currently selected layout_type from their own data source.
 */
trait HasModuleFieldPool
{
    // ── Public Livewire endpoint ──────────────────────────────────────────────

    /**
     * Called by the drag-drop blade via $wire.call('getModuleFields', moduleId, layoutType).
     * Auto-syncs any manually-added DB columns before returning the field pool so
     * newly-migrated columns appear without a rebuild step.
     */
    public function getModuleFields(int $moduleId, ?string $layoutType = null): array
    {
        $module = Module::find($moduleId);
        if ($module) {
            StudioSyncFields::syncSilent($module);
        }

        $layoutType = $layoutType ?? $this->resolveLayoutType();

        return static::loadFieldsForLayout($moduleId, $layoutType);
    }

    // ── Shared query ─────────────────────────────────────────────────────────

    /**
     * Query module_fields for the layout drag-drop pool.
     * System fields are excluded for create/edit layout types only.
     * When $layoutType is null all fields are returned (type not yet chosen).
     */
    protected static function loadFieldsForLayout(int $moduleId, ?string $layoutType): array
    {
        $excludeSystem = in_array($layoutType, ['create', 'edit'], true);

        $query = ModuleField::where('module_id', $moduleId)->orderBy('sort_order');

        if ($excludeSystem) {
            $query->whereNotIn('field_name', FieldTypeMap::SYSTEM_FIELD_NAMES);
        }

        return $query->get(['field_name', 'label'])
            ->map(fn ($f) => ['field_name' => $f->field_name, 'label' => $f->label ?: Str::headline($f->field_name)])
            ->toArray();
    }

    // ── Uniqueness guard ─────────────────────────────────────────────────────

    /**
     * Throw a ValidationException when the layout_type already exists for the module.
     * Pass $ignoreId on edit to exclude the record being updated.
     */
    protected static function ensureLayoutTypeIsUnique(int $moduleId, string $layoutType, ?int $ignoreId = null): void
    {
        $query = ModuleLayout::where('module_id', $moduleId)
            ->where('layout_type', $layoutType);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'data.layout_type' => "A \"{$layoutType}\" layout already exists for this module. Each layout type can only be defined once per module.",
            ]);
        }
    }

    // ── Override point ────────────────────────────────────────────────────────

    /**
     * Return the currently active layout_type, or null when not yet known.
     * Override per page/manager to read from the appropriate data source.
     */
    protected function resolveLayoutType(): ?string
    {
        return null;
    }
}
