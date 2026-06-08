<?php

namespace App\Helpers\Studio;

use App\Models\Module;

class StudioHelper
{
    public static function deploy(Module $module): array
    {
        ModuleValidator::validate($module);

        MigrationGenerator::generate($module);
        ModelGenerator::generate($module);
        ResourceGenerator::generate($module);
        ViewGenerator::generate($module);
        DropdownHandler::createGroup($module);

        $module->update([
            'is_deploy' => true,
            'deployed_at' => now(),
        ]);
        $module->save();

        return [
            'status' => true,
            'msg' => 'Module deployed successfully',
        ];
    }
}