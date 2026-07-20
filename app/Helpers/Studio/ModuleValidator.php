<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Helpers\Studio\FieldTypeMap;
use App\Models\Module;
use RuntimeException;

/**
 * Validates a Module before deployment.
 *
 * Throws RuntimeException on the first failing rule so the caller
 * (StudioManager) can surface a clear, single message to the user.
 */
final class ModuleValidator
{
    public static function validate(Module $module): void
    {
        self::assertNamePresent($module);
        self::assertNameFormat($module);
        self::assertTableNamePresent($module);
        self::assertNotAlreadyDeployed($module);
        self::assertHasFields($module);
        self::assertNoSystemFieldNameConflicts($module);
        self::assertNoDuplicateFieldNames($module);
    }

    // --------------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------------

    private static function assertNamePresent(Module $module): void
    {
        if (empty($module->fullname)) {
            throw new RuntimeException('Module name is required before deployment.');
        }
    }

    private static function assertNameFormat(Module $module): void
    {
        // Must start with a letter; only letters, digits, and underscores allowed.
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', (string) $module->fullname)) {
            throw new RuntimeException(
                "Module name \"{$module->fullname}\" is invalid. "
                . 'Use only letters, digits, and underscores, starting with a letter. '
                . 'Example: CustomerOrder or customer_order.'
            );
        }
    }

    private static function assertTableNamePresent(Module $module): void
    {
        $table = (string) $module->computed_table;

        if ($table === '') {
            throw new RuntimeException(
                'Cannot determine a database table name for this module. '
                . 'Set "Module Name" or "Plural Label" in the module settings before deploying.'
            );
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new RuntimeException(
                "The computed table name \"{$table}\" is invalid. "
                . 'Table names must use only lowercase letters, digits, and underscores. '
                . 'Adjust "Plural Label" or "Module Name" in the module settings.'
            );
        }
    }

    private static function assertNotAlreadyDeployed(Module $module): void
    {
        if ($module->is_deploy) {
            throw new RuntimeException(
                "Module \"{$module->fullname}\" is already deployed."
            );
        }
    }

    private static function assertHasFields(Module $module): void
    {
        if ($module->fields()->count() === 0) {
            throw new RuntimeException(
                "Module \"{$module->fullname}\" has no fields defined. "
                . 'Add at least one field before deploying.'
            );
        }
    }

    private static function assertNoSystemFieldNameConflicts(Module $module): void
    {
        $systemNames = FieldTypeMap::SYSTEM_FIELD_NAMES;

        // Exclude system-seeded records (sort_order >= 9990) — those are created by
        // seedSystemFields() intentionally so they appear in the layout builder.
        // Only block user-defined fields that accidentally share a system column name.
        $conflicts = $module->fields()
            ->whereIn('field_name', $systemNames)
            ->where('sort_order', '<', 9990)
            ->pluck('field_name')
            ->all();

        if (! empty($conflicts)) {
            throw new RuntimeException(
                "Module \"{$module->fullname}\" has fields that conflict with system column names: "
                . implode(', ', $conflicts) . '. Rename these fields before deploying.'
            );
        }
    }

    private static function assertNoDuplicateFieldNames(Module $module): void
    {
        $duplicates = $module->fields()
            ->select('field_name')
            ->groupBy('field_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('field_name')
            ->all();

        if (! empty($duplicates)) {
            throw new RuntimeException(
                "Module \"{$module->fullname}\" has duplicate field names: "
                . implode(', ', $duplicates) . '. Each field name must be unique.'
            );
        }
    }

}
