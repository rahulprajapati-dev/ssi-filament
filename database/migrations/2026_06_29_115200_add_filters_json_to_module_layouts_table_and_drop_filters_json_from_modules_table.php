<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_layouts', function (Blueprint $table) {
            $table->json('filters_json')->nullable()->after('layout_json');
        });

        Schema::table('modules', function (Blueprint $table) {
            if (Schema::hasColumn('modules', 'filters_json')) {
                $table->dropColumn('filters_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('module_layouts', function (Blueprint $table) {
            $table->dropColumn('filters_json');
        });

        Schema::table('modules', function (Blueprint $table) {
            if (! Schema::hasColumn('modules', 'filters_json')) {
                $table->json('filters_json')->nullable()->after('relationships_json');
            }
        });
    }
};
