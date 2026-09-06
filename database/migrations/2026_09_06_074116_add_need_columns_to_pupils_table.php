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
        Schema::table('pupils', function (Blueprint $table) {
            $table->foreignUlid('primary_need_term_id')
                ->nullable()
                ->after('documentation_status')
                ->constrained('need_terms')
                ->nullOnDelete();
            $table->text('primary_need_notes')
                ->nullable()
                ->after('primary_need_term_id');
            $table->foreignUlid('secondary_need_term_id')
                ->nullable()
                ->after('primary_need_notes')
                ->constrained('need_terms')
                ->nullOnDelete();
            $table->text('secondary_need_notes')
                ->nullable()
                ->after('secondary_need_term_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pupils', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_need_term_id');
            $table->dropColumn('primary_need_notes');
            $table->dropConstrainedForeignId('secondary_need_term_id');
            $table->dropColumn('secondary_need_notes');
        });
    }
};
