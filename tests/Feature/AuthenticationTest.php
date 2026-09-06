<?php

namespace Tests\Feature;

use App\Domain\Audit\AuditEventType;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_success_establishes_session_and_authorises_api(): void
    {
        $user = $this->provisionedUser();

        $login = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('message', 'Authenticated.');

        $this->assertAuthenticatedAs($user);

        $this->fromSpa()->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $user->tenant_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::LoginSuccess->value,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function test_login_succeeds_when_email_case_differs_from_stored_value(): void
    {
        $user = $this->provisionedUser([
            'email' => 'Staff.User@Example.COM',
        ]);

        $this->assertSame('staff.user@example.com', $user->email);

        $this->fromSpa()->postJson('/api/v1/login', [
            'email' => 'STAFF.USER@Example.COM',
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_failure_with_wrong_password_returns_generic_401_and_audits(): void
    {
        $user = $this->provisionedUser();

        $response = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ]);

        $this->assertGenericAuthenticationFailure($response);
        $this->assertGuest();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::LoginFailed->value,
            'user_id' => null,
        ]);
    }

    public function test_web_login_failure_with_unknown_email_matches_wrong_password_shape(): void
    {
        $user = $this->provisionedUser();

        $wrongPassword = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ]);

        $unknownEmail = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'not-the-password',
        ]);

        $this->assertGenericAuthenticationFailure($wrongPassword);
        $this->assertGenericAuthenticationFailure($unknownEmail);

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertSame($wrongPassword->status(), $unknownEmail->status());

        $this->assertDatabaseCount('audit_events', 2);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::LoginFailed->value,
        ]);
    }

    public function test_web_logout_ends_session_and_further_api_calls_are_401(): void
    {
        $user = $this->provisionedUser();

        $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $logout = $this->fromSpa()->postJson('/api/v1/logout');

        $logout->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        Auth::forgetGuards();

        $this->fromSpa()->getJson('/api/v1/tenant')->assertUnauthorized();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::Logout->value,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function test_hybrid_token_issue_authorises_bearer_api_calls(): void
    {
        $user = $this->provisionedUser();

        $tokenResponse = $this->postJson('/api/v1/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $tokenResponse->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'token_type']);

        $token = $tokenResponse->json('token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $user->tenant_id);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::LoginSuccess->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_hybrid_token_issue_failure_returns_generic_401_and_audits(): void
    {
        $user = $this->provisionedUser();

        $wrongPassword = $this->postJson('/api/v1/token', [
            'email' => $user->email,
            'password' => 'bad-password',
        ]);

        $unknownEmail = $this->postJson('/api/v1/token', [
            'email' => 'ghost@example.com',
            'password' => 'bad-password',
        ]);

        $this->assertGenericAuthenticationFailure($wrongPassword);
        $this->assertGenericAuthenticationFailure($unknownEmail);
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::LoginFailed->value,
            'user_id' => null,
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => AuditEventType::LoginFailed->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_hybrid_revoke_invalidates_current_bearer_token(): void
    {
        $user = $this->provisionedUser();

        $token = $this->postJson('/api/v1/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'hybrid-client',
        ])->json('token');

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/tenant')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/tenant')
            ->assertUnauthorized();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::Logout->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_protected_api_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/tenant');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertTrue(
            str_contains($response->headers->get('content-type', ''), 'application/json'),
            'Unauthenticated API responses must be JSON'
        );
    }

    public function test_no_public_self_registration_routes_exist(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
        ])->assertNotFound();

        $this->getJson('/api/v1/register')->assertNotFound();
        $this->getJson('/api/v1/signup')->assertNotFound();

        $webRegister = $this->post('/register', []);
        $this->assertTrue(
            in_array($webRegister->status(), [404, 405], true),
            'Public registration must be absent or rejected'
        );
    }

    public function test_csrf_cookie_endpoint_is_available_for_spa(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_sixth_login_attempt_within_minute_returns_429(): void
    {
        $user = $this->provisionedUser();
        $payload = [
            'email' => $user->email,
            'password' => 'not-the-password',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpa()->postJson('/api/v1/login', $payload)->assertUnauthorized();
        }

        $this->fromSpa()->postJson('/api/v1/login', $payload)->assertStatus(429);
    }

    public function test_hybrid_token_without_device_name_defaults_to_hybrid(): void
    {
        $user = $this->provisionedUser();

        $tokenResponse = $this->postJson('/api/v1/token', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $tokenResponse->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'token_type']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'hybrid',
        ]);
    }

    public function test_login_audit_row_includes_ip_and_user_agent(): void
    {
        $user = $this->provisionedUser();

        $this->fromSpa()
            ->withHeaders([
                'User-Agent' => 'GuidelyAuthTest/1.0',
            ])
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::LoginSuccess->value,
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'GuidelyAuthTest/1.0',
        ]);
    }

    public function test_acting_as_still_authorises_protected_routes_under_sanctum(): void
    {
        $user = $this->provisionedUser();

        $this->actingAs($user)
            ->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $user->tenant_id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/schools')->assertOk();
    }

    public function test_deactivated_user_cannot_login_or_issue_token(): void
    {
        $user = $this->provisionedUser(['deactivated_at' => now()]);

        $login = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGenericAuthenticationFailure($login);
        $this->assertGuest();

        $token = $this->postJson('/api/v1/token', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGenericAuthenticationFailure($token);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deactivated_user_matches_bad_password_failure_shape(): void
    {
        $active = $this->provisionedUser([
            'email' => 'active@example.com',
        ]);
        $deactivated = User::factory()->forTenant($active->tenant)->deactivated()->create([
            'email' => 'deactivated@example.com',
            'password' => Hash::make('password'),
        ]);

        $wrongPassword = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $active->email,
            'password' => 'not-the-password',
        ]);

        $deactivatedLogin = $this->fromSpa()->postJson('/api/v1/login', [
            'email' => $deactivated->email,
            'password' => 'password',
        ]);

        $this->assertGenericAuthenticationFailure($wrongPassword);
        $this->assertGenericAuthenticationFailure($deactivatedLogin);
        $this->assertSame($wrongPassword->json(), $deactivatedLogin->json());
    }

    public function test_previously_authenticated_deactivated_user_cannot_call_protected_api(): void
    {
        $user = $this->provisionedUser();
        $token = $user->createToken('hybrid')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/tenant')->assertOk();

        $user->deactivate();
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/tenant')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->actingAs($user->fresh())->getJson('/api/v1/tenant')->assertUnauthorized();
    }

    public function test_middleware_clears_lingering_token_when_deactivated_without_deactivate_helper(): void
    {
        $user = $this->provisionedUser();
        $token = $user->createToken('hybrid')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/tenant')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user->forceFill([
            'deactivated_at' => now(),
        ])->save();

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/tenant')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Simulate a first-party SPA Origin so Sanctum enables session middleware.
     */
    private function fromSpa(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ]);
    }

    /**
     * @param  TestResponse<JsonResponse>  $response
     */
    private function assertGenericAuthenticationFailure($response): void
    {
        $response->assertUnauthorized()
            ->assertExactJson([
                'message' => 'These credentials do not match our records.',
                'code' => 'authentication_failed',
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function provisionedUser(array $overrides = []): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->forTenant($tenant)->create(array_merge([
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
        ], $overrides));
    }
}
