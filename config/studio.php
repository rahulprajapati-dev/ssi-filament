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
    // Valid values: "migration" (default), "schema", "hybrid"
    'mode' => env('STUDIO_MODE', 'migration'),

    /*
    |--------------------------------------------------------------------------
    | Dropdown JSON file path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the JSON file that stores all Studio dropdown option
    | groups. Must be inside storage/ (or another writable directory) so that
    | the PHP process can write to it in production.
    |
    */
    'dropdown_path' => env('STUDIO_DROPDOWN_PATH', null), // resolved at runtime via storage_path()

    /*
    |--------------------------------------------------------------------------
    | Default filesystem disk for file/image fields
    |--------------------------------------------------------------------------
    */
    'default_disk' => env('STUDIO_DEFAULT_DISK', 'public'),

];
