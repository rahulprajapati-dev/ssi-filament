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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100)->unique(); // Accounts
            $table->string('singular_label', 100);
            $table->string('plural_label', 100);
            $table->boolean('is_enable')->default(false);
            $table->boolean('is_deploy')->default(false);
            $table->string('icon')->nullable();

            $table->text('description')->nullable();

            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
