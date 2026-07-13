<?php

namespace App\Traits;

/**
 * Automatically wires up a per-model hook class (e.g. ModuleHook, ModuleFieldHook)
 * as Eloquent event listeners without going through observe(), which would create
 * a new model instance mid-boot and trigger a LogicException in Laravel 11+.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 * @method static void registerModelEvent(string $event, mixed $callback)
 */
trait ModuleHookTrait
{
    protected static function bootModuleHookTrait(): void
    {
        $hookClass = 'App\\Models\\Hooks\\' . class_basename(static::class) . 'Hook';

        if (! class_exists($hookClass)) {
            return;
        }

        $hook = new $hookClass();

        $events = [
            'retrieved',
            'creating',      'created',
            'updating',      'updated',
            'saving',        'saved',
            'deleting',      'deleted',
            'restoring',     'restored',
            'forceDeleting', 'forceDeleted',
            'replicating',
        ];

        foreach ($events as $event) {
            if (method_exists($hook, $event)) {
                // registerModelEvent() is safe to call during boot — no model instantiation.
                static::registerModelEvent($event, [$hook, $event]);
            }
        }
    }
}