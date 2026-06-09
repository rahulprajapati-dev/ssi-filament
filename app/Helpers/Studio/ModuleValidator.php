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
        self::assertNotAlreadyDeployed($module);
        self::assertHasFields($module);
    }

    // --------------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------------

    private static function assertNamePresent(Module $module): void
    {
        if (empty($module->name)) {
            throw new RuntimeException('Module name is required before deployment.');
        }
    }

    private static function assertNameFormat(Module $module): void
    {
        // Must start with a letter; only letters, digits, and underscores allowed.
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', (string) $module->name)) {
            throw new RuntimeException(
                "Module name \"{$module->name}\" is invalid. "
                . 'Use only letters, digits, and underscores, starting with a letter. '
                . 'Example: CustomerOrder or customer_order.'
            );
        }
    }

    private static function assertNotAlreadyDeployed(Module $module): void
    {
        if ($module->is_deploy) {
            throw new RuntimeException(
                "Module \"{$module->name}\" is already deployed."
            );
        }
    }

    private static function assertHasFields(Module $module): void
    {
        if ($module->fields()->count() === 0) {
            throw new RuntimeException(
                "Module \"{$module->name}\" has no fields defined. "
                . 'Add at least one field before deploying.'
            );
        }
    }
}
