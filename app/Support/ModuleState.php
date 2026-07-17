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
     * Clear the cached active-state for a module (or flush all module-state cache entries).
     *
     * This should be called from StudioManager::markDeployed() and
     * StudioManager::markUninstalled() after the module's is_enable flag changes,
     * to prevent stale cache hits on the next request.
     * Example: ModuleState::clear($module->name);
     */
    public static function clear(string $name = null): void
    {
        if ($name !== null) {
            Cache::forget("studio.module.active.{$name}");
        } else {
            Cache::flush();
        }
    }
}