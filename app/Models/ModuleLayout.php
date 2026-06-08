<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModuleHookTrait;
use App\Traits\HasCreatedBy;

class ModuleLayout extends Model
{
    use ModuleHookTrait;
    use HasCreatedBy;

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
