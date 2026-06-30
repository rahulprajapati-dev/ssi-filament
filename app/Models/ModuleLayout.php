<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use App\Traits\ModuleHookTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ModuleLayout extends Model
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
        'layout_type',
        'layout_json',
        'filters_json',
    ];

    protected $casts = [
        'layout_json'  => 'array',
        'filters_json' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
