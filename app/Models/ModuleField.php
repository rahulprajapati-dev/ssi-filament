<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use App\Traits\ModuleHookTrait;
use Illuminate\Database\Eloquent\Model;

class ModuleField extends Model
{
    use HasCreatedBy;
    use ModuleHookTrait;

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
        'sort_order',
        'visibility_mode',
        'condition_logic',
        'always_save_value',
        'visibility_conditions',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'searchable' => 'boolean',
        'sortable' => 'boolean',
        'unique_field' => 'boolean',
        'always_save_value' => 'boolean',
        'visibility_conditions' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
