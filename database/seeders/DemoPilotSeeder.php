<?php

namespace Database\Seeders;

use App\Domain\Identity\Role;
use App\Domain\Ontology\NeedTerm;
use App\Domain\Ontology\PilotOntology;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic Pilot School data for local/manual testing of Epics 1–2.
 *
 * Password for every seeded staff User: {@see self::PASSWORD}
 */
class DemoPilotSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const TENANT_NAME = 'Guidely Demo Primary';

    public const SCHOOL_A_NAME = 'Oak Primary';

    public const SCHOOL_B_NAME = 'Willow Primary';

    /**
     * @var array<string, string>
     */
    public const USERS = [
        'admin' => 'admin@demo.sch.uk',
        'senco' => 'senco@demo.sch.uk',
        'teacher' => 'teacher@demo.sch.uk',
        'support' => 'support@demo.sch.uk',
        'leader' => 'leader@demo.sch.uk',
        'operator' => 'operator@demo.guidely.edu',
    ];

    public function run(): void
    {
        $this->call([
            NeedOntologySeeder::class,
            SettingOntologySeeder::class,
            ProvisionOntologySeeder::class,
            OutcomeOntologySeeder::class,
            ThresholdOntologySeeder::class,
            RelationshipOntologySeeder::class,
        ]);

        $tenant = Tenant::factory()->school()->create([
            'name' => self::TENANT_NAME,
        ]);

        $schoolA = School::factory()->forTenant($tenant)->create([
            'name' => self::SCHOOL_A_NAME,
        ]);
        $schoolB = School::factory()->forTenant($tenant)->create([
            'name' => self::SCHOOL_B_NAME,
        ]);

        $admin = $this->user($tenant, Role::TenantAdmin, 'Demo Admin', self::USERS['admin']);
        $senco = $this->user($tenant, Role::Senco, 'Demo SENCO', self::USERS['senco']);
        $teacher = $this->user($tenant, Role::Teacher, 'Demo Teacher', self::USERS['teacher']);
        $support = $this->user($tenant, Role::SupportStaff, 'Demo Support', self::USERS['support']);
        $leader = $this->user($tenant, Role::SchoolLeader, 'Demo School Leader', self::USERS['leader']);

        $admin->schools()->sync([$schoolA->id, $schoolB->id]);
        $senco->schools()->sync([$schoolA->id, $schoolB->id]);
        $teacher->schools()->sync([$schoolA->id]);
        $support->schools()->sync([$schoolA->id]);
        $leader->schools()->sync([$schoolA->id]);

        $this->user(null, Role::PlatformOperator, 'Demo Platform Operator', self::USERS['operator']);

        app(FeatureFlagResolver::class)->set($tenant, FeatureFlagKey::Connectors, true);

        $pilotVersion = PilotOntology::ensurePublishedVersion();
        $tenant->forceFill([
            'current_ontology_version_id' => $pilotVersion->id,
        ])->save();

        $terms = NeedTerm::query()
            ->forVersion($pilotVersion->id)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        $ci = $terms->get('CI');
        $semh = $terms->get('SEMH');
        $cl = $terms->get('CL');

        $alex = Pupil::factory()->forSchool($schoolA)->senSupport()->withMisKey('MIS-OAK-001')->create([
            'given_name' => 'Alex',
            'family_name' => 'Taylor',
            'year_group' => 'Year 8',
            'date_of_birth' => '2014-03-12',
            'notes' => 'Seeded for Import upsert checks.',
            'primary_need_term_id' => $ci?->id,
            'primary_need_notes' => 'Speech and language focus.',
            'secondary_need_term_id' => $semh?->id,
            'secondary_need_notes' => 'Anxiety around transitions.',
        ]);

        $blake = Pupil::factory()->forSchool($schoolA)->ehcp()->withMisKey('MIS-OAK-002')->create([
            'given_name' => 'Blake',
            'family_name' => 'Nguyen',
            'year_group' => 'Year 9',
            'primary_need_term_id' => $cl?->id,
            'primary_need_notes' => 'Literacy intervention.',
        ]);

        Pupil::factory()->forSchool($schoolA)->create([
            'given_name' => 'Casey',
            'family_name' => 'Patel',
            'year_group' => 'Year 7',
            'send_status' => SendStatus::Neither,
            'mis_key' => null,
        ]);

        Pupil::factory()->forSchool($schoolB)->senSupport()->withMisKey('MIS-WIL-001')->create([
            'given_name' => 'Drew',
            'family_name' => 'Okafor',
            'year_group' => 'Year 10',
            'primary_need_term_id' => $semh?->id,
        ]);

        $left = Pupil::factory()->forSchool($schoolA)->withMisKey('MIS-OAK-LEFT')->create([
            'given_name' => 'Left',
            'family_name' => 'Pupil',
            'year_group' => 'Year 6',
        ]);
        $left->delete();

        $alex->assignTo($teacher, [
            'class_label' => '8B',
            'cohort_label' => 'Wave 1',
        ]);
        $blake->assignTo($teacher, [
            'class_label' => '9A',
        ]);
        $alex->assignTo($support, [
            'class_label' => '8B',
            'cohort_label' => 'Wave 1',
        ]);

        $this->command?->info('Demo Pilot seeded. Sign in with password "'.self::PASSWORD.'":');
        foreach (self::USERS as $label => $email) {
            $this->command?->line("  - {$label}: {$email}");
        }
        $this->command?->line("Schools: {$schoolA->name}, {$schoolB->name}");
        $this->command?->line('Pupils: Alex+Blake assigned to Teacher; Casey unassigned; Drew on Willow; soft-deleted Left Pupil.');
    }

    private function user(?Tenant $tenant, Role $role, string $name, string $email): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'tenant_id' => $tenant?->id,
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
