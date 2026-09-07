<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent duplicate Annual Review (or other type) rows for the same Pupil due date.
     */
    public function up(): void
    {
        Schema::table('review_cycles', function (Blueprint $table) {
            $table->unique(['pupil_id', 'type', 'due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_cycles', function (Blueprint $table) {
            $table->dropUnique(['pupil_id', 'type', 'due_on']);
        });
    }
};
