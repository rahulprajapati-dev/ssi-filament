<?php

namespace App\Models\Hooks;

use App\Models\Module;
use App\Models\ModuleField;

class ModuleHook
{
    public function saving(Module $model): void
    {
        //
    }

    public function saved(Module $model): void
    {
        //
    }

    public function created(Module $model): void
    {
        ModuleField::seedSystemFields($model);
    }
}
