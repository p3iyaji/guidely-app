<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Review Cycles keyed to a Pupil (story 5.1). Written only by Domain\Reviews.
     */
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUlid('pupil_id')
                ->constrained('pupils')
                ->cascadeOnDelete();
            $table->string('type');
            $table->date('due_on');
            $table->boolean('ehcp_linked')->default(false);
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'due_on']);
            $table->index(['pupil_id', 'status', 'due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_cycles');
    }
};
