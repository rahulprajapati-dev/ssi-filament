<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleField extends Model
{

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
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'searchable' => 'boolean',
        'sortable' => 'boolean',
        'unique_field' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
