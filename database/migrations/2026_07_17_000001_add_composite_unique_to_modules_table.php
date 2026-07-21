<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            // Drop the old global unique constraint on name alone.
            $table->dropUnique(['name']);

            // Add a composite unique: the same module name may appear under
            // different keys (e.g. "crm.order" vs "ssi.order"), but each
            // (key, name) pair must be globally unique.
            $table->unique(['key', 'name'], 'uq_modules_key_name');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropUnique('uq_modules_key_name');
            $table->unique('name');
        });
    }
};
