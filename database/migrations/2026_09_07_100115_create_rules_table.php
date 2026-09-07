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
        Schema::create('rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rule_library_version_id')
                ->constrained('rule_library_versions')
                ->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('dimension');
            $table->string('category');
            $table->json('condition');
            $table->json('evaluation');
            $table->json('outcome');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['rule_library_version_id', 'code']);
            $table->index(['rule_library_version_id', 'is_active']);
            $table->index(['rule_library_version_id', 'dimension']);
            $table->index(['rule_library_version_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
