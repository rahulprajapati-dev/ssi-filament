<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use App\Traits\ModuleHookTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Module extends Model
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
        'key',
        'name',
        'singular_label',
        'plural_label',
        'icon',
        'description',
        'relationships_json',
        'use_uuid',
        'is_relationships',
        'is_deploy',
        'is_enable',
        'deployed_at',
    ];

    protected $casts = [
        'is_deploy' => 'boolean',
        'is_relationships' => 'boolean',
        'is_enable' => 'boolean',
        'use_uuid' => 'boolean',
        'deployed_at' => 'datetime',
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
    protected function fullname(): Attribute
    {
        return Attribute::make(
            get: function () {
                $key = trim($this->key ?? '');
                $name = trim($this->name ?? '');
                return !empty($key) ? $key . '_' . $name : $name;
            }
        );
    }
}
