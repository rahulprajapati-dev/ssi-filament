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

            $table->string('icon')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_enable')->default(false)->index();
            $table->boolean('is_deploy')->default(false)->index();

            // Correct timestamp type
            $table->timestamp('deployed_at')->nullable();

            // Proper user relations
            $table->string('created_by', 36)->nullable();
            $table->string('updated_by', 36)->nullable();
            // $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

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
