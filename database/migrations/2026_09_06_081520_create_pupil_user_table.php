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
        Schema::create('pupil_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('pupil_id')->constrained('pupils')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('class_label')->nullable();
            $table->string('cohort_label')->nullable();
            $table->string('source')->default('senco');
            $table->timestamps();

            $table->unique(['pupil_id', 'user_id']);
            $table->index(['user_id', 'pupil_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pupil_user');
    }
};
