<?php
namespace App\Helpers\Studio;

use App\Models\Module;
use Exception;

class ModuleValidator
{
    public static function validate(Module $module): void
    {
        if (empty($module->name)) {
            throw new Exception("Module name is required");
        }

        if ($module->is_deploy) {
            throw new Exception("Module already deployed");
        }
    }
}