<?php

namespace App\Console\Commands;

use App\Services\DbSchemaExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSchema extends Command
{
    protected $signature = 'schema:generate {table} {Resource} {--connection=mysql} {--action=table}';

    protected $description = 'Export a table schema to a Studio JSON config file.';

    public function handle()
    {
        $table      = $this->argument('table');
        $Resource   = $this->argument('Resource');
        $connection = $this->option('connection');
        $action     = $this->option('action');
        $outputDir  = base_path('app/Filament/Resources');

        $paths = [
            'table'   => "{$outputDir}/{$Resource}/Tables/listView.json",
            'default' => "{$outputDir}/{$Resource}/Schemas/default.json",
            'create'  => "{$outputDir}/{$Resource}/Schemas/createView.json",
            'edit'    => "{$outputDir}/{$Resource}/Schemas/editView.json",
            'detail'  => "{$outputDir}/{$Resource}/Schemas/detailView.json",
        ];

        if (! isset($paths[$action])) {
            $this->error("Invalid action: {$action}. Valid values: " . implode(', ', array_keys($paths)));
            return 1;
        }

        $path = $paths[$action];

        if (File::exists($path)) {
            if (! $this->confirm("Schema for '{$table}' already exists at {$path}. Overwrite?")) {
                $this->info('Export cancelled.');
                return 0;
            }
        }

        try {
            $schema = DbSchemaExporter::export($table, $connection);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($schema, JSON_PRETTY_PRINT));
            $this->info("Exported schema for '{$table}' (connection: {$connection}) to {$path}");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
