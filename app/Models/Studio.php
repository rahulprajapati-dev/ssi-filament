<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['module_name', 'meta_data', 'description', 'created_by', 'updated_by', 'created_at'])]
class Studio extends Model
{
    use HasCreatedBy;

    protected $fillable = [
        'module_name',
        'description',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];
    //
}
 