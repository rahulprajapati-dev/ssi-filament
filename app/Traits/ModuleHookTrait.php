<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait ModuleHookTrait
{
    protected static function bootModuleHookTrait(): void
    {
        $modelClass = class_basename(static::class);

        $hookTrait = "App\\Models\\Hooks\\{$modelClass}Hook";

        if (trait_exists($hookTrait)) {

            $method = 'boot' . class_basename(str_replace('\\', '/', $hookTrait));

            if (method_exists(static::class, $method)) {
                forward_static_call([static::class, $method]);
            }
        }
    }
}