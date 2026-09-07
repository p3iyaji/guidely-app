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
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignUlid('current_rule_library_version_id')
                ->nullable()
                ->after('current_ontology_version_id')
                ->constrained('rule_library_versions')
                ->nullOnDelete();
        });

        $pilotId = $this->ensurePilotRuleLibraryVersion();

        if ($pilotId !== null) {
            DB::table('tenants')
                ->whereNull('current_rule_library_version_id')
                ->update([
                    'current_rule_library_version_id' => $pilotId,
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
            $table->dropConstrainedForeignId('current_rule_library_version_id');
        });
    }

    /**
     * Ensure a published Pilot Rule Library version row exists for Tenant pin backfill.
     */
    private function ensurePilotRuleLibraryVersion(): ?string
    {
        if (! Schema::hasTable('rule_library_versions')) {
            return null;
        }

        $now = now();
        $existing = DB::table('rule_library_versions')
            ->where('code', PilotRuleLibrary::VERSION_CODE)
            ->first();

        if ($existing !== null) {
            DB::table('rule_library_versions')->where('id', $existing->id)->update([
                'label' => PilotRuleLibrary::VERSION_LABEL,
                'status' => 'published',
                'published_at' => $existing->published_at ?? $now,
                'updated_at' => $now,
            ]);

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

        return $id;
    }
};
