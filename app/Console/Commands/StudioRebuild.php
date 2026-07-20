<?php

namespace App\Console\Commands;

use App\Helpers\Studio\StudioManager;
use App\Models\Module;
use Illuminate\Console\Command;

class StudioRebuild extends Command
{
    protected $signature = 'studio:rebuild {module : The module name (fullname) to rebuild}';

    protected $description = 'Repair & Rebuild a Studio module (regenerates files from current layout records).';

    public function handle(): int
    {
        $name   = $this->argument('module');
        $module = Module::where('name', $name)->orWhere('fullname', $name)->first();

        if (! $module) {
            $this->error("Module '{$name}' not found.");
            return self::FAILURE;
        }

        $this->info("Rebuilding module '{$module->fullname}'…");

        $result = StudioManager::rebuild($module);

        if ($result->success) {
            $this->info($result->message);
            if (! empty($result->generated)) {
                $this->line('  Generated: ' . implode(', ', $result->generated));
            }
            if (! empty($result->skipped)) {
                $this->line('  Skipped:   ' . implode(', ', $result->skipped));
            }
            return self::SUCCESS;
        }

        $this->error($result->message);
        return self::FAILURE;
    }
}
