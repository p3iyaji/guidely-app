<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Open/resolved Indicator alerts (story 7.4). Written by Domain\Reporting evaluate.
     */
    public function up(): void
    {
        Schema::create('compliance_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('scope');
            $table->foreignUlid('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnDelete();
            $table->string('metric');
            $table->float('threshold_value');
            $table->float('observed_value');
            $table->boolean('is_open')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_open', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_alerts');
    }
};
