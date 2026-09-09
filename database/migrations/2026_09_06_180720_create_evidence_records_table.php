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
        Schema::create('evidence_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('pupil_id')->constrained('pupils')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('lifecycle');
            $table->timestamp('occurred_at');
            $table->foreignUlid('setting_term_id')->constrained('setting_terms')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['tenant_id', 'pupil_id', 'occurred_at']);
            $table->index(['tenant_id', 'type', 'lifecycle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_records');
    }
};
