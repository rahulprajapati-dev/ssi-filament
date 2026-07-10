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
        // Schema::table('module_fields', function (Blueprint $table) {
        //     $table->smallInteger('sort_order')->nullable()->change();
        // });
        Schema::table('modules', function (Blueprint $table) {
            $table->string('key',10)->nullable()->after('uuid');
        });
        /*// One layout_type per module (create / edit / detail / list)


        // One layout_type per module (create / edit / detail / list)
        Schema::table('module_layouts', function (Blueprint $table) {
            if (! Schema::hasColumn('module_layouts','inherit_edit_layout')) {
                $table->boolean('inherit_edit_layout')->default(false)->after('filters_json');
            }
            if (! Schema::hasColumn('module_layouts','inherit_detail_layout')) {
                $table->boolean('inherit_detail_layout')->default(false)->after('inherit_edit_layout');
            }
        });*/
    }

    public function down(): void
    {
        
    }
};