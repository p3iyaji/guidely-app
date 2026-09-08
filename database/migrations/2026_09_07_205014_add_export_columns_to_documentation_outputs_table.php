<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Export metadata for encrypted portable bundles (story 5.4).
     */
    public function up(): void
    {
        Schema::table('documentation_outputs', function (Blueprint $table) {
            $table->text('purpose')->nullable();
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('byte_size')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentation_outputs', function (Blueprint $table) {
            $table->dropColumn([
                'purpose',
                'file_disk',
                'file_path',
                'checksum',
                'byte_size',
            ]);
        });
    }
};
