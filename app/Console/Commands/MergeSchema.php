<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SchemaMerger;
use Illuminate\Support\Facades\File;

class MergeSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema:merge {table} {brand}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description:Merge a default and brand-specific schema.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');
        $brand = $this->argument('brand');
        $outputDir = base_path("schemas/merged/{$brand}");
        $path = "{$outputDir}/{$table}.json";

        try {
            $mergedSchema = SchemaMerger::merge($table, $brand);

            if (!File::exists($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }

            File::put($path, json_encode($mergedSchema, JSON_PRETTY_PRINT));
            $this->info("Successfully merged schema for '{$table}' for brand '{$brand}' at {$path}");

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
