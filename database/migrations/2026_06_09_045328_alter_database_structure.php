<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*Schema::table('modules', function (Blueprint $table) {
            // Correct timestamp type
            $table->timestamp('deployed_at')->nullable()->after('is_deploy');
        });*/

        // One field_name per module (case-insensitive check is handled at the app layer)
        Schema::table('module_fields', function (Blueprint $table) {
            $table->unique(['module_id', 'field_name'], 'uq_module_fields_module_field');
        });

        // One layout_type per module (create / edit / detail / list)
        Schema::table('module_layouts', function (Blueprint $table) {
            $table->unique(['module_id', 'layout_type'], 'uq_module_layouts_module_type');
        });
    }
};