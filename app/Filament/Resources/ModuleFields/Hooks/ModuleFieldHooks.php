<?php

namespace App\Filament\Resources\ModuleFields\Hooks;

use App\Helpers\Studio\FieldTypeMap;
use App\Models\ModuleField;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ModuleFieldHooks
{
    /**
     * Validate field on change.
     */
    public function filedValidation($set, $get, $record)
    {
        $fieldName = $get('field_name');

        if (in_array(strtolower((string) $fieldName), FieldTypeMap::SYSTEM_FIELD_NAMES, true)) {
            return [
                'status' => true,
                'error'  => "\"{$fieldName}\" is a reserved system field and cannot be added manually.",
            ];
        }

        $validator = Validator::make(
            [
                'field_name' => $fieldName,
            ],
            [
                'field_name' => [
                    'max:50',
                    'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                ],
            ],
            [
                'max' => 'Field Name may not be greater than 50 characters.',
                'regex' => 'Field Name must start with a letter and may only contain letters, numbers, and underscores.',
            ]
        );

        if ($validator->fails()) {
            return [
                'status' => true,
                'error' => $validator->errors()->first('field_name'),
            ];
        }else {
            $set('label',  Str::headline($fieldName));
        }
    }
}