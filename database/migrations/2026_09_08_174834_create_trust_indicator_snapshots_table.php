<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly Trust Indicator aggregates for flagged portfolio trends (story 7.3).
     * Written only by Domain\Reporting snapshotting — not SRE.
     */
    public function up(): void
    {
        Schema::create('trust_indicator_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnDelete();
            $table->string('month', 7);
            $table->string('snapshot_key');
            $table->unsignedInteger('pupils_in_scope')->default(0);
            $table->unsignedInteger('gaps')->default(0);
            $table->float('gap_density');
            $table->float('lateness_rate');
            $table->unsignedInteger('overdue_open_cycles')->default(0);
            $table->unsignedInteger('open_cycles')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'snapshot_key']);
            $table->index(['tenant_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trust_indicator_snapshots');
    }
};
