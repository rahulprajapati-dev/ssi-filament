<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasCreatedBy;

    protected $fillable = [
        'name',
        'singular_label',
        'plural_label',
        'icon',
        'description',
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
