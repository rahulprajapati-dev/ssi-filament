<?php

namespace App\Console\Commands;

use App\Services\DbSchemaExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenrateSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:genrate {table} {--connection=mysql} {Resource} {--action=table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export a table schema to a default JSON file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');
        $connection = $this->option('connection');
        devfatal($connection);
        $action = $this->option('action');
        $Resource = $this->argument('Resource');
        $outputDir = base_path("app/Filament/Resources");
        $paths = [
            'table' => "{$outputDir}/{$Resource}/Tables/listView.json",
            'default' => "{$outputDir}/{$Resource}/Schemas/defaultView.json",
            'create' => "{$outputDir}/{$Resource}/Schemas/createView.json",
            'edit' => "{$outputDir}/{$Resource}/Schemas/editView.json",
            'detail' => "{$outputDir}/{$Resource}/Schemas/detailView.json",
        ];

        if (!isset($paths[$action])) {
            $this->error("Invalid action: {$action}");
            return 1;
        }
        $path = $paths[$action];
        devfatal($path);
        if (File::exists($path)) {
            if (!$this->confirm("Schema for '{$table}' already exists. Do you want to overwrite it?")) {
                $this->info("Export cancelled.");
                return 0;
            }
        }

        try {
            $schema = DbSchemaExporter::export($table, $connection);
            if (!File::exists($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }
            File::put($path, json_encode($schema, JSON_PRETTY_PRINT));
            $this->info("Successfully exported schema for '{$table}' from connection '{$connection}' to {$path}");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
