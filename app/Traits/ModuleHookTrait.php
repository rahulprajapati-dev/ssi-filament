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
        $baseName = class_basename(static::class) . 'Hook';

        $hookClass = null;
        foreach ([
            'App\\Custom\\Models\\Hooks\\' . $baseName,
            'App\\Models\\Hooks\\Custom\\' . $baseName,
            'App\\Models\\Hooks\\' . $baseName,
        ] as $candidate) {
            if (class_exists($candidate)) {
                $hookClass = $candidate;
                break;
            }
        }

        if ($hookClass === null) {
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