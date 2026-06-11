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
        Schema::table('module_fields', function (Blueprint $table) {
            $table->string('visibility_mode', 30)->default('always_visible');
            $table->string('condition_logic', 10)->default('and');
            $table->boolean('always_save_value')->default(false);
            $table->json('visibility_conditions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('module_fields', function (Blueprint $table) {
            $table->dropColumn(['visibility_mode', 'condition_logic', 'always_save_value', 'visibility_conditions']);
        });
    }
};
