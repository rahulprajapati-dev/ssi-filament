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
            if (! Schema::hasColumn('modules', 'filters_json')) {
                $table->json('filters_json')->nullable()->after('relationships_json');
            }
            if (! Schema::hasColumn('modules','is_relationships')) {
                $table->boolean('is_relationships')->default(false)->after('use_uuid');
            }

        });*/
        // One field_name per module (case-insensitive check is handled at the app layer)
        Schema::table('module_fields', function (Blueprint $table) {
            $table->smallInteger('sort_order')->nullable()->change();
        });

        /*// One layout_type per module (create / edit / detail / list)
        Schema::table('module_layouts', function (Blueprint $table) {
            $table->unique(['module_id', 'layout_type'], 'uq_module_layouts_module_type');
        });*/
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('filters_json');
        });
    }
};
