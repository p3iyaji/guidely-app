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
            $table->dropForeign(['setting_term_id']);
        });

        Schema::table('evidence_records', function (Blueprint $table) {
            $table->ulid('setting_term_id')->nullable()->change();
            $table->foreign('setting_term_id')
                ->references('id')
                ->on('setting_terms')
                ->restrictOnDelete();

            $table->foreignUlid('provision_term_id')
                ->nullable()
                ->after('setting_term_id')
                ->constrained('provision_terms')
                ->restrictOnDelete();

            $table->text('body')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidence_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provision_term_id');
        });

        Schema::table('evidence_records', function (Blueprint $table) {
            $table->dropForeign(['setting_term_id']);
        });

        Schema::table('evidence_records', function (Blueprint $table) {
            $table->ulid('setting_term_id')->nullable(false)->change();
            $table->foreign('setting_term_id')
                ->references('id')
                ->on('setting_terms')
                ->restrictOnDelete();

            $table->text('body')->nullable(false)->change();
        });
    }
};
