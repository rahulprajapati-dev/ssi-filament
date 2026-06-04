<?php

namespace App\Console\Commands;

use App\Services\DbSchemaExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:export {table} {--connection=mysql}';

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
        $outputDir = base_path('schemas/default');
        $path = "{$outputDir}/{$table}.json";

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
