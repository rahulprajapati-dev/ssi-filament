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

    public function saved(ModuleField $model): void
    {
        if (! FieldTypeMap::isAddressType($model->type)) {
            return;
        }

        $suffixes = array_keys(FieldTypeMap::ADDRESS_SUB_FIELDS);

        if ($model->wasChanged('field_name')) {
            $oldName = $model->getOriginal('field_name');
            $newName = $model->field_name;

            foreach ($suffixes as $suffix) {
                ModuleField::where('module_id', $model->module_id)
                    ->where('field_name', $oldName . $suffix)
                    ->update(['field_name' => $newName . $suffix]);
            }
        }

        if ($model->wasChanged('label')) {
            $subLabels = [
                '_street1' => 'Street 1',
                '_street2' => 'Street 2',
                '_city'    => 'City',
                '_state'   => 'State',
                '_pincode' => 'Pincode',
            ];
            $baseName = $model->field_name;
            $baseLabel = $model->label ?? $baseName;

            foreach ($suffixes as $suffix) {
                ModuleField::where('module_id', $model->module_id)
                    ->where('field_name', $baseName . $suffix)
                    ->update(['label' => $baseLabel . ' (' . $subLabels[$suffix] . ')']);
            }
        }
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
