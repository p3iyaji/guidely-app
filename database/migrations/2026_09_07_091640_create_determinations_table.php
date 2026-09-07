<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Minimal citation stub for Ontology version immutability (story 4.4).
     * Full SRE write path is story 4.6.
     */
    public function up(): void
    {
        Schema::create('determinations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('pupil_id')
                ->nullable()
                ->constrained('pupils')
                ->nullOnDelete();
            $table->foreignUlid('ontology_version_id')
                ->constrained('ontology_versions')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'ontology_version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('determinations');
    }
};
