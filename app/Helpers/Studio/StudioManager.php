<?php

declare(strict_types=1);

namespace App\Helpers\Studio;

use App\Helpers\Studio\DropdownHandler;
use App\Models\Module;
use Illuminate\Support\Facades\Artisan;

/**
 * StudioManager — main deployment orchestrator.
 *
 * Usage:
 *   $result = StudioManager::deploy($module);
 *   if ($result->success) { ... }
 *
 * Design notes:
 *  - Private constructor enforces the static factory pattern.
 *  - Each generator step returns bool (true = file created, false = skipped).
 *    Legacy generators that return void are treated as "generated".
 *  - All exceptions are caught and converted to a failed DeploymentResult,
 *    so call-sites never need to wrap this in their own try/catch.
 */
final class StudioManager
{
    /** Steps that produced new files this run. */
    private array $generated = [];

    /** Steps whose output already existed on disk (idempotent skip). */
    private array $skipped = [];

    private function __construct(private readonly Module $module) {}

    // ─── Public API ──────────────────────────────────────────────────────────

    public static function deploy(Module $module): DeploymentResult
    {
        return (new self($module))->run();
    }

    /**
     * Rebuild JSON schema files for an already-deployed module.
     * Skips all validators and the already-deployed check.
     * Safe to call multiple times; always overwrites JSON files from layout records.
     */
    public static function rebuild(Module $module): DeploymentResult
    {
        return (new self($module))->runRebuild();
    }

    // ─── Internal pipeline ───────────────────────────────────────────────────

    private function run(): DeploymentResult
    {
        try {
            ModuleValidator::validate($this->module);

            $this->step('migration', fn () => MigrationGenerator::generate($this->module));
            $this->step('migrate',   fn () => $this->runMigrations());
            $this->step('model',     fn () => ModelGenerator::generate($this->module));
            $this->step('resource',  fn () => ResourceGenerator::generate($this->module));
            $this->step('layouts',   fn () => LayoutGenerator::generate($this->module));

            DropdownHandler::createGroup($this->module->name);

            $this->markDeployed();

            return DeploymentResult::ok(
                "Module '{$this->module->name}' deployed successfully.",
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
            // Re-generate the migration file if it was deleted; idempotent if it exists.
            $this->step('migration', fn () => MigrationGenerator::generate($this->module));
            $this->step('migrate',   fn () => $this->runMigrations());
            $this->step('layouts',   fn () => LayoutGenerator::generate($this->module, force: true));

            return DeploymentResult::ok(
                "Module '{$this->module->name}' rebuilt successfully. Table and schemas are up to date.",
                $this->generated,
                $this->skipped,
            );

        } catch (\Throwable $e) {
            return DeploymentResult::fail($e->getMessage());
        }
    }

    /**
     * Run all pending migrations.
     * --force is required when APP_ENV=production to skip the console confirmation.
     * Returns true so the step is recorded as "generated" (migrations applied).
     */
    private function runMigrations(): bool
    {
        Artisan::call('migrate', ['--force' => true]);
        return true;
    }

    /**
     * Run a single generator step and record whether it generated or skipped.
     *
     * Generators SHOULD return:
     *   true  — new file(s) written
     *   false — all files already existed; step skipped
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

    /**
     * Persist the deployed state.  Called only after all generators succeed.
     */
    private function markDeployed(): void
    {
        $this->module->update([
            'is_deploy'   => true,
            'deployed_at' => now(),
        ]);
    }
}
