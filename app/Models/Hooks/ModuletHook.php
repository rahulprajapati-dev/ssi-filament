<?php

namespace App\Models\Traits\Hooks;

use Illuminate\Database\Eloquent\Model;

trait ModuleHook
{
    protected static function bootModuleHook(): void
    {
        static::saving(function (Model $model) {

            devfatal('Contact Saving');

        });

        static::saved(function (Model $model) {

            devfatal('Contact Saved');

        });
    }
}