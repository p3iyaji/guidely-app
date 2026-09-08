<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configured Indicator thresholds for flagged compliance alerts (story 7.4).
     */
    public function up(): void
    {
        Schema::create('compliance_alert_thresholds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnDelete();
            $table->string('metric');
            $table->float('threshold');
            $table->string('snapshot_key');
            $table->timestamps();

            $table->unique(['tenant_id', 'snapshot_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_alert_thresholds');
    }
};
