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
        Schema::create('outcome_terms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ontology_version_id')
                ->constrained('ontology_versions')
                ->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ontology_version_id', 'code']);
            $table->index(['ontology_version_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outcome_terms');
    }
};
