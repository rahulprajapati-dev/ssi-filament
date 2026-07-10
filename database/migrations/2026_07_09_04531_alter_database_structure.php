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
        Schema::table('module_layouts', function (Blueprint $table) {
            if (! Schema::hasColumn('module_layouts','inherit_edit_layout')) {
                $table->boolean('inherit_edit_layout')->default(false)->after('filters_json');
            }
            if (! Schema::hasColumn('module_layouts','inherit_detail_layout')) {
                $table->boolean('inherit_detail_layout')->default(false)->after('inherit_edit_layout');
            }
        });
    }

    public function down(): void
    {
        
    }
};
