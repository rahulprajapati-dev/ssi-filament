<?php

namespace App\Support;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;

class ModuleState
{
    public static function active(string $name): bool
    {
        return Module::where('name', $name)->where('is_enable', true)->exists();
    }

    public static function clear(string $name): void
    {

    }
}