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
        Schema::table('schools', function (Blueprint $table) {
            $table->string('address')->nullable()->after('name');
            $table->string('postcode', 16)->nullable()->after('address');
            $table->string('city')->nullable()->after('postcode');
            $table->string('county')->nullable()->after('city');
            $table->string('country')->nullable()->after('county');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['address', 'postcode', 'city', 'county', 'country']);
        });
    }
};
