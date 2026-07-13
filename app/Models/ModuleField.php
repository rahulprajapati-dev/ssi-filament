<?php

namespace App\Models;

use App\Helpers\Studio\FieldTypeMap;
use App\Traits\HasCreatedBy;
use App\Traits\ModuleHookTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ModuleField extends Model
{
    use HasCreatedBy;
    use ModuleHookTrait;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'module_id',
        'field_name',
        'label',
        'type',
        'length',
        'required',
        'searchable',
        'sortable',
        'unique_field',
        'default_value',
        'options',
        'is_multiple',
        'sort_order',
        'visibility_mode',
        'condition_logic',
        'always_save_value',
        'visibility_conditions',
    ];

    protected $casts = [
        'options'               => 'array',
        'is_multiple'           => 'boolean',
        'required'              => 'boolean',
        'searchable'            => 'boolean',
        'sortable'              => 'boolean',
        'unique_field'          => 'boolean',
        'always_save_value'     => 'boolean',
        'sort_order'            => 'integer',
        'visibility_conditions' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * When an address field is created, auto-create one module_fields row per sub-field
     * (street1, street2, city, state, pincode) so they appear individually in the layout builder.
     * Each sub-field is stored as type='text' and its DB column is created by normal generators.
     * Uses firstOrCreate so repeated calls are safe.
     */
    public static function seedAddressSubFields(self $parent): void
    {
        $subLabels = [
            '_street1' => 'Street 1',
            '_street2' => 'Street 2',
            '_city'    => 'City',
            '_state'   => 'State',
            '_pincode' => 'Pincode',
        ];

        $baseSort = ($parent->sort_order ?? 0);
        $offset   = 1;

        foreach (FieldTypeMap::ADDRESS_SUB_FIELDS as $suffix => $length) {
            $colName = $parent->field_name . $suffix;
            $label   = ($parent->label ?? $parent->field_name) . ' (' . $subLabels[$suffix] . ')';

            static::firstOrCreate(
                ['module_id' => $parent->module_id, 'field_name' => $colName],
                [
                    'module_id'  => $parent->module_id,
                    'field_name' => $colName,
                    'label'      => $label,
                    'type'       => 'text',
                    'length'     => $length,
                    'required'   => ($suffix !== '_street2') && (bool) $parent->required,
                    'sort_order' => $baseSort + $offset,
                ],
            );

            $offset++;
        }
    }

    /**
     * Ensure the four implicit system columns (created_by, updated_by, created_at, updated_at)
     * always have a corresponding module_fields row so they appear in the layout builder.
     * Uses firstOrCreate so it is safe to call multiple times.
     */
    public static function seedSystemFields(Module $module): void
    {
        $systemFields = [
            ['field_name' => 'created_by', 'label' => 'Created By', 'type' => 'text',     'length' => 36, 'sort_order' => 9990],
            ['field_name' => 'updated_by', 'label' => 'Updated By', 'type' => 'text',     'length' => 36, 'sort_order' => 9991],
            ['field_name' => 'created_at', 'label' => 'Created At', 'type' => 'datetime', 'length' => 0,  'sort_order' => 9992],
            ['field_name' => 'updated_at', 'label' => 'Updated At', 'type' => 'datetime', 'length' => 0,  'sort_order' => 9993],
        ];

        foreach ($systemFields as $data) {
            static::firstOrCreate(
                ['module_id' => $module->id, 'field_name' => $data['field_name']],
                array_merge(['module_id' => $module->id], $data),
            );
        }
    }
}
