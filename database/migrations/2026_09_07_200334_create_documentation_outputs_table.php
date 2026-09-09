<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Versioned Documentation Outputs bound to a Pupil and Review Cycle (story 5.3).
     * Written only by Domain\Outputs.
     */
    public function up(): void
    {
        Schema::create('documentation_outputs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('pupil_id')
                ->constrained('pupils')
                ->cascadeOnDelete();
            $table->foreignUlid('review_cycle_id')
                ->constrained('review_cycles')
                ->restrictOnDelete();
            $table->string('type');
            $table->unsignedInteger('version');
            $table->foreignId('confirmer_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('disclaimer_text');
            $table->timestamp('confirmed_at');
            $table->timestamp('pack_ready_at');
            $table->json('payload');
            $table->timestamps();

            $table->index(
                ['tenant_id', 'pupil_id', 'review_cycle_id', 'type'],
                'doc_outputs_lookup_idx'
            );            
            $table->unique(
                ['pupil_id', 'review_cycle_id', 'type', 'version'],
                'doc_outputs_version_unique'
            );        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_outputs');
    }
};
