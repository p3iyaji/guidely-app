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
        Schema::create('pupils', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('given_name');
            $table->string('family_name');
            $table->string('mis_key')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('year_group');
            $table->string('send_status')->default('neither');
            $table->string('documentation_status')->default('not-started');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'mis_key']);
            $table->index(['tenant_id', 'school_id']);
            $table->index(['tenant_id', 'family_name', 'given_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pupils');
    }
};
