<?php

namespace App\Console\Commands;

use App\Services\SchemaDiffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiffSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:diff {table} {brand} {--connection=oem}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a diff schema for a specific brand based on the default.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');
        $brand = $this->argument('brand');
        $connection = $this->option('connection');
        $outputDir = base_path("schemas/{$brand}");
        $path = "{$outputDir}/{$table}.json";

        try {
            $diffSchema = SchemaDiffer::diff($table, $connection);

            if (empty($diffSchema['fields'])) {
                $this->info("No differences found for table '{$table}'. No file generated.");
                return 0;
            }

            if (!File::exists($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }

            File::put($path, json_encode($diffSchema, JSON_PRETTY_PRINT));
            $this->info("Successfully generated diff schema for '{$table}' for brand '{$brand}' at {$path}");

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
