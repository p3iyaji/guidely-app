<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only Overrides keyed by Pupil+dimension (story 4.9).
     * Written only by Domain\Sre — not mass-assignable from capture APIs.
     */
    public function up(): void
    {
        Schema::create('overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('pupil_id')
                ->constrained('pupils')
                ->cascadeOnDelete();
            $table->foreignUlid('determination_id')
                ->constrained('determinations')
                ->restrictOnDelete();
            $table->string('dimension');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('rationale');
            $table->timestamps();

            $table->index(['tenant_id', 'pupil_id', 'dimension']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overrides');
    }
};
