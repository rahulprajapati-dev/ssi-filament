<?php

namespace App\Helpers\Studio;

use App\Models\Module;
use App\Helpers\Studio\DropdownHandler;

class StudioHelper
{
    public static function deploy(Module $module): array
    {
        try {
            ModuleValidator::validate($module);

            MigrationGenerator::generate($module);
            ModelGenerator::generate($module);
            ResourceGenerator::generate($module);
            ViewGenerator::generate($module);
            $fields = $module->fields;
            foreach ($fields as $field) {
                if ($field->type == 'select') {
                    DropdownHandler::createGroup($module->name,$field->field_name,$field->options);
                }
            }
            $module->update([
                'is_deploy' => true,
                'deployed_at' => now(),
            ]);

            return [
                'status' => true,
                'msg' => "Module '{$module->name}' deployed successfully.",
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'msg' => $e->getMessage(),
            ];
        }
    }
}