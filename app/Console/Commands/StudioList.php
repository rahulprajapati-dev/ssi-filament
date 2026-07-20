<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

class StudioList extends Command
{
    protected $signature = 'studio:list {--deployed : Show only deployed modules} {--pending : Show only undeployed modules}';

    protected $description = 'List all Studio modules with their deployment status.';

    public function handle(): int
    {
        $query = Module::orderBy('key')->orderBy('name');

        if ($this->option('deployed')) {
            $query->where('is_deploy', true);
        } elseif ($this->option('pending')) {
            $query->where('is_deploy', false);
        }

        $modules = $query->get(['id', 'key', 'name', 'fullname', 'plural_label', 'is_deploy', 'is_active']);

        if ($modules->isEmpty()) {
            $this->info('No modules found.');
            return self::SUCCESS;
        }

        $rows = $modules->map(fn ($m) => [
            $m->id,
            $m->key ?: '—',
            $m->fullname ?: $m->name,
            $m->plural_label ?: '—',
            $m->is_deploy ? '<info>Deployed</info>' : '<comment>Pending</comment>',
            $m->is_active ? 'Active' : 'Inactive',
        ])->all();

        $this->table(
            ['ID', 'Key', 'Name', 'Plural Label', 'Status', 'Active'],
            $rows
        );

        return self::SUCCESS;
    }
}
