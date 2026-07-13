<?php

namespace App\Models\Hooks;

use App\Helpers\Studio\FieldTypeMap;
use App\Models\ModuleField;

class ModuleFieldHook
{
    public function saving(ModuleField $_model): void
    {
        //
    }

    public function saved(ModuleField $_model): void
    {
        //
    }

    public function created(ModuleField $model): void
    {
        if (FieldTypeMap::isAddressType($model->type)) {
            ModuleField::seedAddressSubFields($model);
        }
    }

    public function deleted(ModuleField $model): void
    {
        if (FieldTypeMap::isAddressType($model->type)) {
            $suffixes = array_keys(FieldTypeMap::ADDRESS_SUB_FIELDS);
            $subNames = array_map(fn ($s) => $model->field_name . $s, $suffixes);

            ModuleField::where('module_id', $model->module_id)
                ->whereIn('field_name', $subNames)
                ->delete();
        }
    }
}
