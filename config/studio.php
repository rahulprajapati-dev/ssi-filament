<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Studio Deployment Mode
    |--------------------------------------------------------------------------
    |
    | Controls how the Studio Module Builder handles database schema changes
    | when a module is deployed or rebuilt.
    |
    | "migration" (default)
    |     Generates a migration file and runs `php artisan migrate`.
    |     Provides full rollback history and is the safest option for
    |     production deployments.
    |
    | "schema"
    |     Uses Laravel's Schema Builder directly — no migration files are
    |     created.  Columns are added on the fly.  Ideal for rapid prototyping
    |     or environments where migration files are not needed.
    |
    | "hybrid"
    |     Generates the migration file (version history) AND immediately
    |     applies schema changes via SchemaSyncService so the table is
    |     up to date without waiting for a separate migrate step.
    |
    */
    'mode' => env('STUDIO_MODE', 'migration'),

];
