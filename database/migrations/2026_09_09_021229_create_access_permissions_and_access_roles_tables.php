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
        Schema::create('access_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('group')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'is_system']);
        });

        Schema::create('access_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'is_system']);
        });

        Schema::create('access_permission_role', function (Blueprint $table) {
            $table->foreignUlid('access_role_id')
                ->constrained('access_roles')
                ->cascadeOnDelete();
            $table->foreignUlid('access_permission_id')
                ->constrained('access_permissions')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['access_role_id', 'access_permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_permission_role');
        Schema::dropIfExists('access_roles');
        Schema::dropIfExists('access_permissions');
    }
};
