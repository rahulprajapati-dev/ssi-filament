<?php

namespace App\Support;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;

class ModuleState
{
    public static function active(string $name): bool
    {
        return Cache::remember(
            "module_active_{$name}",
            now()->addMinutes(30),
            function () use ($name) {
                return Module::where('name', $name)->where('is_enable', true)->exists();
            }
        );
    }

    public static function clear(string $name): void
    {
        Cache::forget("module_active_{$name}");
    }
}