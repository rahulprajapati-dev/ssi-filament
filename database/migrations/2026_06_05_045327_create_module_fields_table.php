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
        Schema::create('module_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 100);
            $table->string('label', 100);
            $table->string('type', 50);
            $table->integer('length')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('searchable')->default(false);
            $table->boolean('sortable')->default(false);
            $table->boolean('unique_field')->default(false);
            $table->text('default_value')->nullable();
            $table->json('options')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_fields');
    }
};
