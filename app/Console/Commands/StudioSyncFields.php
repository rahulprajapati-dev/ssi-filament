<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\Studio\FieldTypeMap;
use App\Models\Module;
use App\Models\ModuleField;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Syncs raw database columns into module_fields so unregistered columns
 * (added via manual migrations, not through Studio) become visible in
 * the Studio layout builder.
 *
 * Also called automatically by StudioManager::run() and StudioManager::runRebuild()
 * via the static syncSilent() method so that manually-added columns surface
 * without requiring a separate manual command invocation.
 *
 * Usage:
 *   php artisan studio:sync-fields              # all deployed modules
 *   php artisan studio:sync-fields employees    # one module by fullname
 *   php artisan studio:sync-fields --dry-run    # preview without writing
 */
class StudioSyncFields extends Command
{
    protected $signature = 'studio:sync-fields
                            {module? : Module fullname (key_name). Omit to sync all deployed modules.}
                            {--dry-run : Preview what would be added without writing to the database.}';

    protected $description = 'Sync unregistered DB table columns into module_fields so they appear in the layout builder.';

    // ── Artisan entry-point ───────────────────────────────────────────────────

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry-run');

        if ($isDry) {
            $this->line('<fg=yellow>DRY RUN — no changes will be written.</>');
        }

        $modules = $this->resolveModules();

        if ($modules->isEmpty()) {
            $this->warn('No deployed modules found.');
            return self::SUCCESS;
        }

        $totalAdded = 0;

        foreach ($modules as $module) {
            $rows = self::collectRows($module);

            $fullname = $module->fullname;
            $table    = $module->computed_table;

            $this->newLine();
            $this->line("<fg=white;options=bold>Module:</> {$fullname}  <fg=gray>→ table: {$table}</>");

            if (empty($rows)) {
                $this->line('  <fg=gray>No unregistered columns found.</>');
                continue;
            }

            $this->table(
                ['Field name', 'Label', 'Studio type', 'Sort order', 'Required'],
                array_map(fn ($r) => [
                    $r['field_name'],
                    $r['label'],
                    $r['type'],
                    $r['sort_order'],
                    $r['required'] ? 'yes' : 'no',
                ], $rows),
            );

            if (! $isDry) {
                self::insertRows($module, $rows);
                $count = count($rows);
                $this->line("  <fg=green>✓ Added {$count} field(s).</>");
            }

            $totalAdded += count($rows);
        }

        $this->newLine();

        if ($isDry) {
            $this->line("<fg=cyan>Would add {$totalAdded} field(s) total.</>");
        } else {
            $this->info("Done. Added {$totalAdded} field(s) total.");
        }

        return self::SUCCESS;
    }

    // ── Called by StudioManager (no console output) ───────────────────────────

    /**
     * Run the field sync for one module without any console output.
     * Returns the number of fields added (0 when table missing or nothing new).
     */
    public static function syncSilent(Module $module): int
    {
        $rows = self::collectRows($module);

        if (empty($rows)) {
            return 0;
        }

        self::insertRows($module, $rows);

        return count($rows);
    }

    // ── Shared core logic (static — usable by both paths) ────────────────────

    /**
     * Compute the list of DB columns that are not yet in module_fields.
     * Returns an array of row arrays ready for insertRows().
     *
     * @return array<int, array{field_name:string, label:string, type:string, sort_order:int, required:bool}>
     */
    private static function collectRows(Module $module): array
    {
        $table = $module->computed_table;

        if (! Schema::hasTable($table)) {
            return [];
        }

        $existingFields = ModuleField::where('module_id', $module->id)
            ->pluck('field_name')
            ->all();

        $addressFieldNames = ModuleField::where('module_id', $module->id)
            ->where('type', 'address')
            ->pluck('field_name')
            ->all();

        $skipNames = array_merge(
            ['id', 'uuid'],
            FieldTypeMap::SYSTEM_FIELD_NAMES,
            $existingFields,
        );

        $maxOrder = ModuleField::where('module_id', $module->id)
            ->where('sort_order', '<', 9990)
            ->max('sort_order') ?? 0;

        $rows = [];

        foreach (Schema::getColumns($table) as $col) {
            $name = $col['name'];

            if (in_array($name, $skipNames, true)) {
                continue;
            }

            if (self::isAddressSubColumn($name, $addressFieldNames)) {
                continue;
            }

            $maxOrder += 10;

            $rows[] = [
                'field_name' => $name,
                'label'      => Str::headline($name),
                'type'       => FieldTypeMap::fromDbType(
                    $col['type_name'] ?? '',
                    $col['type']      ?? '',
                    $name,
                ),
                'sort_order' => $maxOrder,
                'required'   => ! ($col['nullable'] ?? true) && ($col['default'] === null),
            ];
        }

        return $rows;
    }

    /** Persist the collected rows into module_fields. */
    private static function insertRows(Module $module, array $rows): void
    {
        foreach ($rows as $row) {
            ModuleField::create([
                'module_id'  => $module->id,
                'field_name' => $row['field_name'],
                'label'      => $row['label'],
                'type'       => $row['type'],
                'sort_order' => $row['sort_order'],
                'required'   => $row['required'],
            ]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Collection<int, Module> */
    private function resolveModules()
    {
        $name = $this->argument('module');

        if ($name) {
            $all    = Module::where('is_deploy', true)->get();
            $module = $all->first(fn ($m) => $m->fullname === $name);

            if (! $module) {
                $this->error("Module \"{$name}\" not found or not deployed.");
                exit(self::FAILURE);
            }

            return collect([$module]);
        }

        return Module::where('is_deploy', true)->get();
    }

    /**
     * Returns true when $columnName is a DB sub-column generated by an address-type field.
     *
     * @param  string[]  $addressFieldNames
     */
    private static function isAddressSubColumn(string $columnName, array $addressFieldNames): bool
    {
        foreach ($addressFieldNames as $prefix) {
            foreach (array_keys(FieldTypeMap::ADDRESS_SUB_FIELDS) as $suffix) {
                if ($columnName === $prefix . $suffix) {
                    return true;
                }
            }
        }

        return false;
    }
}
