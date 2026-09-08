<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Current minimised safeguarding context per Pupil (story 7.5). Not a case file.
     */
    public function up(): void
    {
        Schema::create('safeguarding_signals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('pupil_id')
                ->unique()
                ->constrained('pupils')
                ->cascadeOnDelete();
            $table->boolean('present');
            $table->string('severity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safeguarding_signals');
    }
};
