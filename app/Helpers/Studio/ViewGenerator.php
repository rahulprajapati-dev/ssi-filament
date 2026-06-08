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
}