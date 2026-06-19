<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Module;

class ViewGenerator
{
    public static function generate(Module $module): bool
    {
        $folder = resource_path('views/modules/' . Str::snake($module->name));
        $file   = $folder . '/index.blade.php';

        if (File::exists($file)) {
            return false;
        }

        if (! File::exists($folder)) {
            File::makeDirectory($folder, 0755, true);
        }

        File::put($file, "<h1>{$module->name}</h1>");

        return true;
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