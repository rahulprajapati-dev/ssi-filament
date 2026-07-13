<?php

namespace App\Models\Hooks;

use App\Models\Module;

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
}
