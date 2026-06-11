<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Helpers\Studio\DropdownHandler;
use App\Helpers\Studio\SchemaSyncService;
use App\Models\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * StudioManager — main deployment orchestrator.
 *
 * Supports three schema-management modes via config('studio.mode'):
 *
 *   "migration" (default)
 *       Generates a migration file and runs `php artisan migrate`.
 *       Full rollback history; safe for production.
 *
 *   "schema"
 *       Uses SchemaSyncService to apply schema changes directly.
 *       No migration files are created.
 *
 *   "hybrid"
 *       Generates the migration file (version history) AND immediately
 *       applies schema changes so the table is ready without a separate
 *       migrate step.
 *
 * Usage:
 *   $result = StudioManager::deploy($module);
 *   $result = StudioManager::rebuild($module);
 *   $result = StudioManager::uninstall($module, $data);
 */
final class StudioManager
{
    private const VALID_MODES = ['migration', 'schema', 'hybrid'];

    /** Steps that produced new files / schema changes this run. */
    private array $generated = [];

    /** Steps whose output already existed on disk (idempotent skip). */
    private array $skipped = [];

    private function __construct(
        private readonly Module $module,
        private readonly array $data = [],
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    public static function deploy(Module $module): DeploymentResult
    {
        return (new self($module))->run();
    }

    /**
     * Repair an already-deployed module.
     * - Syncs the database table to current field definitions (no migration file).
     * - Regenerates all JSON schema files from current layout records.
     */
    public static function rebuild(Module $module): DeploymentResult
    {
        return (new self($module))->runRebuild();
    }

    /**
     * Uninstall a deployed module.
     * Removes generated files, resources, migrations, and (optionally) the table.
     */
    public static function uninstall(Module $module, array $data = []): DeploymentResult
    {
        return (new self($module, $data))->runUninstall();
    }

    // ─── Internal pipelines ───────────────────────────────────────────────────

    private function run(): DeploymentResult
    {
        try {
            ModuleValidator::validate($this->module);

            $mode = self::resolvedMode();

            // ── Schema steps (mode-dependent) ──────────────────────────────────
            if ($mode === 'migration' || $mode === 'hybrid') {
                $this->step('migration', fn () => MigrationGenerator::generate($this->module));
                $this->step('migrate',   fn () => $this->runMigrations());
            }

            if ($mode === 'schema' || $mode === 'hybrid') {
                $this->step('schema_sync', function () {
                    SchemaSyncService::sync($this->module);
                    return true;
                });
            }

            // ── File generation steps (always run) ────────────────────────────
            $this->step('model',    fn () => ModelGenerator::generate($this->module));
            $this->step('resource', fn () => ResourceGenerator::generate($this->module));
            $this->step('layouts',  fn () => LayoutGenerator::generate($this->module));
            $module=$this->module;
             $fields = $module->fields;
            foreach ($fields as $field) {
                if ($field->type == 'select') {
                    DropdownHandler::createGroup($module->name,$field->field_name,$field->options);
                }
            }

            $this->markDeployed();

            return DeploymentResult::ok(
                "Module '{$this->module->name}' deployed successfully. (mode: {$mode})",
                $this->generated,
                $this->skipped,
            );

        } catch (\Throwable $e) {
            return DeploymentResult::fail($e->getMessage());
        }
    }

    private function runRebuild(): DeploymentResult
    {
        try {
            // Rebuild always uses SchemaSyncService regardless of mode —
            // the goal is to bring the table in sync instantly, not re-run migrations.
            $this->step('schema_sync', function () {
                SchemaSyncService::sync($this->module);
                return true;
            });

            // Regenerate JSON schema files from the current ModuleLayout records.
            $this->step('layouts', fn () => LayoutGenerator::generate($this->module, force: true));

            return DeploymentResult::ok(
                "Module '{$this->module->name}' rebuilt successfully. Table and schemas are up to date.",
                $this->generated,
                $this->skipped,
            );

        } catch (\Throwable $e) {
            return DeploymentResult::fail($e->getMessage());
        }
    }

    private function runUninstall(): DeploymentResult
    {
        try {
            $this->step('remove_layouts',   fn () => LayoutGenerator::remove($this->module));
            $this->step('remove_resource',  fn () => ResourceGenerator::remove($this->module));
            $this->step('remove_model',     fn () => ModelGenerator::remove($this->module));
            $this->step('remove_views',     fn () => ViewGenerator::remove($this->module));
            $this->step('remove_migration', fn () => MigrationGenerator::remove($this->module));

            if (($this->data['is_table'] ?? false) === true) {
                $this->step('drop_table', fn () => $this->dropTable());
            }

            $this->markUninstalled();

            return DeploymentResult::ok(
                "Module '{$this->module->name}' uninstalled successfully.",
                $this->generated,
                $this->skipped,
            );

        } catch (\Throwable $e) {
            return DeploymentResult::fail($e->getMessage());
        }
    }

    // ─── Step runner ──────────────────────────────────────────────────────────

    /**
     * Run a single generator step and record whether it generated or skipped.
     *
     * Generators SHOULD return:
     *   true  — new file(s) written / change applied
     *   false — already existed / nothing to do; step skipped
     *   void/null — treated as "generated" for backward compatibility
     */
    private function step(string $name, \Closure $fn): void
    {
        $result = $fn();

        if ($result === false) {
            $this->skipped[] = $name;
        } else {
            $this->generated[] = $name;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Read and validate the studio.mode config value.
     * Falls back to "migration" for any unrecognised value.
     */
    private static function resolvedMode(): string
    {
        $mode = (string) config('studio.mode', 'migration');

        return in_array($mode, self::VALID_MODES, true) ? $mode : 'migration';
    }

    /**
     * Run all pending migrations.
     * --force is required when APP_ENV=production to skip the console confirmation.
     */
    private function runMigrations(): bool
    {
        Artisan::call('migrate', ['--force' => true]);
        return true;
    }

    private function markDeployed(): void
    {
        $this->module->update([
            'is_deploy'   => true,
            'deployed_at' => now(),
        ]);
    }

    private function markUninstalled(): void
    {
        $this->module->update([
            'is_deploy'   => false,
            'deployed_at' => null,
        ]);
    }

    private function dropTable(): bool
    {
        $table = Str::snake(Str::plural($this->module->name));
        if (! Schema::hasTable($table)) {
            return false;
        }
        Schema::dropIfExists($table);
        return true;
    }
}
