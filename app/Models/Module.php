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
            get: fn () => implode('_', array_filter([$this->key, $this->name,])),
        );
    }
    protected function fullnamelable(): Attribute
    {
         return Attribute::make(
        get: fn () => filled($this->key)
            ? Str::studly($this->key) . ' ' . $this->plural_label
            : $this->plural_label
        );
    }
    protected function table(): Attribute
    {
        return Attribute::make(get: fn () => strtolower(str_replace(' ', '_', implode('_', array_filter([$this->key, $this->plural_label])))));
    }
    
}
