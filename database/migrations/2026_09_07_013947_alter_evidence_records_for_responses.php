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
        Schema::table('evidence_records', function (Blueprint $table) {
            $table->foreignUlid('related_intervention_id')
                ->nullable()
                ->after('provision_term_id')
                ->constrained('evidence_records')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidence_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_intervention_id');
        });
    }
};
