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
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('sso_enabled')->default(false)->after('cohort_label');
            $table->string('sso_provider')->nullable()->after('sso_enabled');
            $table->string('sso_entity_id')->nullable()->after('sso_provider');
            $table->string('sso_client_id')->nullable()->after('sso_entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'sso_enabled',
                'sso_provider',
                'sso_entity_id',
                'sso_client_id',
            ]);
        });
    }
};
