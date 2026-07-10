<?php

namespace App\Filament\Resources\ModuleFields\Hooks;

use App\Models\ModuleField;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Validator;

class ModuleFieldHooks
{
    /**
     * Validate field on change.
     */
    public function filedValidation($set, $get, $record)
    {
        $fieldName = $get('field_name');

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
            $set('label', $fieldName);
        }
    }
}