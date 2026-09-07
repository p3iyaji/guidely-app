<?php

use App\Domain\Ontology\PilotRuleLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Citation stub for Rule Library version immutability (story 4.5).
     * Full SRE write path is story 4.6.
     */
    public function up(): void
    {
        $pilotId = $this->requirePublishedPilotRuleLibraryVersionId();

        Schema::table('determinations', function (Blueprint $table) use ($pilotId) {
            $table->foreignUlid('rule_library_version_id')
                ->after('ontology_version_id')
                ->default($pilotId)
                ->constrained('rule_library_versions')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'rule_library_version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('determinations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'rule_library_version_id']);
            $table->dropConstrainedForeignId('rule_library_version_id');
        });
    }

    /**
     * Resolve (or create) the published Pilot Rule Library version id for citation backfill.
     *
     * @throws RuntimeException When the Rule Library table is missing or the Pilot id cannot be resolved
     */
    private function requirePublishedPilotRuleLibraryVersionId(): string
    {
        if (! Schema::hasTable('rule_library_versions')) {
            throw new RuntimeException(
                'Cannot add determinations.rule_library_version_id: rule_library_versions table is missing.',
            );
        }

        $now = now();
        $existing = DB::table('rule_library_versions')
            ->where('code', PilotRuleLibrary::VERSION_CODE)
            ->first();

        if ($existing !== null) {
            if ($existing->status !== 'published' || $existing->published_at === null) {
                DB::table('rule_library_versions')->where('id', $existing->id)->update([
                    'label' => PilotRuleLibrary::VERSION_LABEL,
                    'status' => 'published',
                    'published_at' => $existing->published_at ?? $now,
                    'updated_at' => $now,
                ]);
            }

            return $existing->id;
        }

        $id = (string) Str::ulid();
        DB::table('rule_library_versions')->insert([
            'id' => $id,
            'code' => PilotRuleLibrary::VERSION_CODE,
            'label' => PilotRuleLibrary::VERSION_LABEL,
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = DB::table('rule_library_versions')->where('id', $id)->first();

        if ($created === null) {
            throw new RuntimeException(
                'Cannot add determinations.rule_library_version_id: failed to create Pilot Rule Library version.',
            );
        }

        return $id;
    }
};
