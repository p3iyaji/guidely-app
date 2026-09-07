<?php

use App\Domain\Ontology\PilotOntology;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignUlid('current_ontology_version_id')
                ->nullable()
                ->after('sso_client_id')
                ->constrained('ontology_versions')
                ->nullOnDelete();
        });

        $unifiedId = $this->consolidateLegacyOntologyStubs();

        if ($unifiedId !== null) {
            DB::table('tenants')
                ->whereNull('current_ontology_version_id')
                ->update([
                    'current_ontology_version_id' => $unifiedId,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_ontology_version_id');
        });
    }

    /**
     * Move Need/Setting/Provision terms from per-domain stub versions onto one Pilot version.
     *
     * @return string|null Unified Pilot Ontology version id when available
     */
    private function consolidateLegacyOntologyStubs(): ?string
    {
        if (! Schema::hasTable('ontology_versions')) {
            return null;
        }

        $now = now();
        $unified = DB::table('ontology_versions')
            ->where('code', PilotOntology::VERSION_CODE)
            ->first();

        if ($unified === null) {
            $unifiedId = (string) Str::ulid();
            DB::table('ontology_versions')->insert([
                'id' => $unifiedId,
                'code' => PilotOntology::VERSION_CODE,
                'label' => PilotOntology::VERSION_LABEL,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $unifiedId = $unified->id;
            DB::table('ontology_versions')->where('id', $unifiedId)->update([
                'label' => PilotOntology::VERSION_LABEL,
                'status' => 'published',
                'published_at' => $unified->published_at ?? $now,
                'updated_at' => $now,
            ]);
        }

        $termTables = ['need_terms', 'setting_terms', 'provision_terms'];

        foreach (PilotOntology::LEGACY_VERSION_CODES as $legacyCode) {
            $legacy = DB::table('ontology_versions')->where('code', $legacyCode)->first();

            if ($legacy === null || $legacy->id === $unifiedId) {
                continue;
            }

            foreach ($termTables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $terms = DB::table($table)->where('ontology_version_id', $legacy->id)->get();

                foreach ($terms as $term) {
                    $survivor = DB::table($table)
                        ->where('ontology_version_id', $unifiedId)
                        ->where('code', $term->code)
                        ->first();

                    if ($survivor !== null) {
                        $this->remapTermForeignKeys($table, $term->id, $survivor->id);
                        DB::table($table)->where('id', $term->id)->delete();
                    } else {
                        DB::table($table)->where('id', $term->id)->update([
                            'ontology_version_id' => $unifiedId,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            DB::table('ontology_versions')->where('id', $legacy->id)->delete();
        }

        return $unifiedId;
    }

    /**
     * Retarget pupil / evidence FKs from a doomed legacy term onto the surviving unified term.
     */
    private function remapTermForeignKeys(string $termTable, string $fromTermId, string $toTermId): void
    {
        if ($fromTermId === $toTermId) {
            return;
        }

        if ($termTable === 'need_terms' && Schema::hasTable('pupils')) {
            DB::table('pupils')->where('primary_need_term_id', $fromTermId)->update([
                'primary_need_term_id' => $toTermId,
            ]);
            DB::table('pupils')->where('secondary_need_term_id', $fromTermId)->update([
                'secondary_need_term_id' => $toTermId,
            ]);
        }

        if ($termTable === 'setting_terms' && Schema::hasTable('evidence_records')) {
            DB::table('evidence_records')->where('setting_term_id', $fromTermId)->update([
                'setting_term_id' => $toTermId,
            ]);
        }

        if ($termTable === 'provision_terms' && Schema::hasTable('evidence_records')) {
            DB::table('evidence_records')->where('provision_term_id', $fromTermId)->update([
                'provision_term_id' => $toTermId,
            ]);
        }
    }
};
