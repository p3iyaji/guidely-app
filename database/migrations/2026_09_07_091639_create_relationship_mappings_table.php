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
        Schema::create('relationship_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ontology_version_id')
                ->constrained('ontology_versions')
                ->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('relationship_type');
            $table->string('from_domain');
            $table->ulid('from_term_id');
            $table->string('to_domain');
            $table->ulid('to_term_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ontology_version_id', 'code']);
            $table->index(['ontology_version_id', 'is_active']);
            $table->index(['from_domain', 'from_term_id']);
            $table->index(['to_domain', 'to_term_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationship_mappings');
    }
};
