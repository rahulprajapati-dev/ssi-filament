<?php

namespace App\Helpers\Studio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Module;

class ViewGenerator
{
    public static function generate(Module $module): void
    {
        $folder = resource_path('views/modules/' . Str::snake($module->name));

        if (!File::exists($folder)) {
            File::makeDirectory($folder, 0755, true);
        }

        File::put($folder.'/index.blade.php', "<h1>{$module->name}</h1>");
    }

    public static function remove(Module $module): bool
    {
        $folder = resource_path('views/modules/' . Str::snake($module->name));
        $file   = $folder . '/index.blade.php';

        if (! File::exists($file)) {
            return false;
        }

        File::delete($file);

        if (File::isDirectory($folder) && empty(File::files($folder))) {
            File::deleteDirectory($folder);
        }

        return true;
    }
}