<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModuleHookTrait;
use App\Traits\HasCreatedBy;

class ModuleField extends Model
{
    use ModuleHookTrait;
    use HasCreatedBy;

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
