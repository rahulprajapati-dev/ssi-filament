<?php

namespace App\Support;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;

class ModuleState
{
    public static function active(string $name): bool
    {
        return Cache::remember("studio.module.active.{$name}", 300, fn () =>
            Module::where('name', $name)->where('is_enable', true)->exists()
        );
    }

    /**
     * Clear the cached active-state for a module (or all module-state entries).
     *
     * Pass a module name to clear that module's entry only.
     * Pass null to clear all known module-state entries without touching
     * the rest of the application cache.
     * Example: ModuleState::clear($module->name);
     */
    public static function clear(?string $name = null): void
    {
        if ($name !== null) {
            Cache::forget("studio.module.active.{$name}");
        } else {
            // Clear only studio module-state keys — never flush the whole cache.
            foreach (Module::pluck('name') as $moduleName) {
                Cache::forget("studio.module.active.{$moduleName}");
            }
        }
    }
}