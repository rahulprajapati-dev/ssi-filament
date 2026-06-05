<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleLayout extends Model
{
    protected $fillable = [
        'module_id',
        'layout_type',
        'layout_json',
    ];

    protected $casts = [
        'layout_json' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
