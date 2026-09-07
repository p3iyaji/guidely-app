<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Expands Determinations for the deterministic SRE evaluator (story 4.6).
     * Citation FKs remain immutable; evaluator columns hold result + pathway.
     */
    public function up(): void
    {
        Schema::table('determinations', function (Blueprint $table) {
            $table->string('dimension')->nullable()->after('pupil_id');
            $table->string('result')->nullable()->after('dimension');
            $table->foreignUlid('rule_id')
                ->nullable()
                ->after('result')
                ->constrained('rules')
                ->restrictOnDelete();
            $table->json('reasoning_pathway')->nullable()->after('rule_id');
            $table->boolean('is_current')->default(false)->after('reasoning_pathway');
            $table->timestamp('evaluated_at')->nullable()->after('is_current');

            $table->index(['pupil_id', 'dimension', 'is_current']);
            $table->index(['tenant_id', 'pupil_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('determinations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'pupil_id', 'is_current']);
            $table->dropIndex(['pupil_id', 'dimension', 'is_current']);
            $table->dropConstrainedForeignId('rule_id');
            $table->dropColumn([
                'dimension',
                'result',
                'reasoning_pathway',
                'is_current',
                'evaluated_at',
            ]);
        });
    }
};
