<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use App\Traits\ModuleHookTrait;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasCreatedBy;
    use ModuleHookTrait;

    protected $fillable = [
        'name',
        'singular_label',
        'plural_label',
        'icon',
        'description',
        'relationships_json',
        'is_deploy',
        'is_enable',
        'deployed_at',
    ];

    protected $casts = [
        'is_deploy'          => 'boolean',
        'is_enable'          => 'boolean',
        'deployed_at'        => 'datetime',
        'relationships_json' => 'array',
    ];

    public function fields()
    {
        return $this->hasMany(ModuleField::class);
    }

    public function layouts()
    {
        return $this->hasMany(ModuleLayout::class);
    }
}
