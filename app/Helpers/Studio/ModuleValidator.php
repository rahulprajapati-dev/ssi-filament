<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

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
        $table = (string) $module->table;

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
}
