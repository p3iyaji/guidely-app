<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Derived Gaps from current SRE Determinations (story 4.7).
     * Written only by Domain\Sre — not hand-edited APIs.
     */
    public function up(): void
    {
        Schema::create('gaps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('pupil_id')
                ->constrained('pupils')
                ->cascadeOnDelete();
            $table->foreignUlid('determination_id')
                ->nullable()
                ->constrained('determinations')
                ->nullOnDelete();
            $table->string('dimension');
            $table->string('result');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'pupil_id', 'is_open']);
            $table->index(['pupil_id', 'is_open']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gaps');
    }
};
