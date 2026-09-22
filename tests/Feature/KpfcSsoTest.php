<?php

namespace Tests\Feature;

use App\Models\SsoEventReceipt;
use App\Models\User;
use App\Models\UserSsoToken;
use App\Services\Sso\KpfcSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KpfcSsoTest extends TestCase
{
    use RefreshDatabase;

    protected string $issuer = 'https://admin-staging.kpfcbuilders.com';

    protected string $clientId = '01a0c3e5-0bcc-71f7-877f-d82dfb0f1d6c';

    protected string $clientSecret = '8DtnTzQel3oZ9kqRV7wwyykrGFp7gFnOVCro26oS';

    protected string $webhookSecret = 'staging_webhook_secret_fleet_2026';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kpfc_sso.issuer' => $this->issuer,
            'services.kpfc_sso.client_id' => $this->clientId,
            'services.kpfc_sso.client_secret' => $this->clientSecret,
            'services.kpfc_sso.redirect_uri' => 'http://localhost:8000/auth/kpfc/callback',
            'services.kpfc_sso.scopes' => 'fleet:login fleet:profile',
            'services.kpfc_sso.webhook_secret' => $this->webhookSecret,
        ]);
    }

    /**
     * Test the login view renders with the sign-in button.
     */
    public function test_login_page_renders_with_sign_in_button(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Sign in with KPFC Admin');
        $response->assertSee(route('auth.kpfc.redirect'));
    }

    /**
     * Test the redirect initiates PKCE, stores state and verifier in session, and redirects to issuer.
     */
    public function test_redirect_initiates_pkce_and_redirects_to_issuer(): void
    {
        $response = $this->get('/auth/kpfc/redirect');

        $response->assertStatus(302);
        $this->assertTrue(session()->has('kpfc_sso_state'));
        $this->assertTrue(session()->has('kpfc_code_verifier'));

        $targetUrl = $response->headers->get('Location');
        $this->assertStringStartsWith($this->issuer.'/oauth/authorize', $targetUrl);

        parse_str((string) parse_url($targetUrl, PHP_URL_QUERY), $queryParams);

        $this->assertSame($this->clientId, $queryParams['client_id']);
        $this->assertSame('http://localhost:8000/auth/kpfc/callback', $queryParams['redirect_uri']);
        $this->assertSame('code', $queryParams['response_type']);
        $this->assertSame('fleet:login fleet:profile', $queryParams['scope']);
        $this->assertSame('S256', $queryParams['code_challenge_method']);
        $this->assertSame(session('kpfc_sso_state'), $queryParams['state']);
        $this->assertNotEmpty($queryParams['code_challenge']);
    }

    /**
     * Test first login creates exactly one shadow user and encrypted token record.
     */
    public function test_first_login_creates_exactly_one_shadow_user(): void
    {
        $state = 'test_state_12345';
        $verifier = 'test_verifier_abcdef1234567890abcdef1234567890';
        $sub = 'bca7909f-cdf0-4f59-a69d-6cb61f7d34a6';

        Http::fake([
            $this->issuer.'/oauth/token' => Http::response([
                'access_token' => 'access_token_mock_123',
                'refresh_token' => 'refresh_token_mock_456',
                'token_type' => 'Bearer',
                'expires_in' => 900,
            ], 200),
            $this->issuer.'/api/sso/user' => Http::response([
                'sub' => $sub,
                'name' => 'Leah Example',
                'email' => 'leah@example.com',
                'phone' => '+254700000000',
                'role' => 'admin',
                'fleet_access' => true,
                'updated_at' => '2026-09-21T09:00:00+03:00',
            ], 200),
        ]);

        $response = $this->withSession([
            'kpfc_sso_state' => $state,
            'kpfc_code_verifier' => $verifier,
        ])->get("/auth/kpfc/callback?code=test_code&state={$state}");

        $response->assertRedirect('/');

        // User authenticated
        $this->assertTrue(Auth::check());
        $user = Auth::user();
        $this->assertNotNull($user);

        // Exactly one shadow user created
        $this->assertSame(1, User::count());
        $this->assertSame($sub, $user->kpfc_sub);
        $this->assertSame('Leah Example', $user->name);
        $this->assertSame('leah@example.com', $user->email);
        $this->assertSame('+254700000000', $user->phone);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->fleet_access);

        // Tokens stored server-side and encrypted
        $tokenRecord = $user->ssoToken;
        $this->assertNotNull($tokenRecord);
        $this->assertSame('access_token_mock_123', $tokenRecord->access_token);
        $this->assertSame('refresh_token_mock_456', $tokenRecord->refresh_token);

        // Verify raw database token is not stored in plaintext
        $rawRow = DB::table('user_sso_tokens')->where('user_id', $user->id)->first();
        $this->assertNotSame('access_token_mock_123', $rawRow->access_token);

        // Verify session consumed state and verifier (single-use)
        $this->assertFalse(session()->has('kpfc_sso_state'));
        $this->assertFalse(session()->has('kpfc_code_verifier'));
    }

    /**
     * Test repeat login links to the same shadow user by sub even after email changes.
     */
    public function test_repeat_login_links_to_same_shadow_user_by_sub(): void
    {
        $sub = 'bca7909f-cdf0-4f59-a69d-6cb61f7d34a6';

        // Pre-create shadow user with older details
        $existing = User::create([
            'kpfc_sub' => $sub,
            'name' => 'Old Name',
            'email' => 'old_email@example.com',
            'role' => 'user',
            'fleet_access' => true,
        ]);

        $state = 'test_state_repeat';
        $verifier = 'test_verifier_repeat';

        Http::fake([
            $this->issuer.'/oauth/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'token_type' => 'Bearer',
                'expires_in' => 900,
            ], 200),
            $this->issuer.'/api/sso/user' => Http::response([
                'sub' => $sub,
                'name' => 'Leah Updated',
                'email' => 'leah.updated@example.com',
                'phone' => '+254711111111',
                'role' => 'admin',
                'fleet_access' => true,
                'updated_at' => '2026-09-22T09:00:00+03:00',
            ], 200),
        ]);

        $response = $this->withSession([
            'kpfc_sso_state' => $state,
            'kpfc_code_verifier' => $verifier,
        ])->get("/auth/kpfc/callback?code=test_code_2&state={$state}");

        $response->assertRedirect('/');

        // Same user updated, not a new row
        $this->assertSame(1, User::count());
        $user = User::first();
        $this->assertSame($existing->id, $user->id);
        $this->assertSame('Leah Updated', $user->name);
        $this->assertSame('leah.updated@example.com', $user->email);
        $this->assertSame('+254711111111', $user->phone);
        $this->assertSame('admin', $user->role);
    }

    /**
     * Test callback fails with state mismatch or missing verifier.
     */
    public function test_callback_fails_on_state_mismatch_or_missing_verifier(): void
    {
        // 1. Wrong state
        $response = $this->withSession([
            'kpfc_sso_state' => 'expected_state',
            'kpfc_code_verifier' => 'verifier_123',
        ])->get('/auth/kpfc/callback?code=abc&state=tampered_state');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');

        // 2. Missing verifier
        $response = $this->withSession([
            'kpfc_sso_state' => 'expected_state_2',
        ])->get('/auth/kpfc/callback?code=abc&state=expected_state_2');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');
    }

    /**
     * Test login is denied if user lacks fleet_access.
     */
    public function test_login_denied_when_fleet_access_is_false(): void
    {
        $state = 'state_denied';
        $verifier = 'verifier_denied';

        Http::fake([
            $this->issuer.'/oauth/token' => Http::response([
                'access_token' => 'access_token_denied',
                'refresh_token' => 'refresh_token_denied',
                'token_type' => 'Bearer',
                'expires_in' => 900,
            ], 200),
            $this->issuer.'/api/sso/user' => Http::response([
                'sub' => 'denied-user-sub',
                'name' => 'Denied User',
                'email' => 'denied@example.com',
                'fleet_access' => false,
            ], 200),
        ]);

        $response = $this->withSession([
            'kpfc_sso_state' => $state,
            'kpfc_code_verifier' => $verifier,
        ])->get("/auth/kpfc/callback?code=code_denied&state={$state}");

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');
        $this->assertFalse(Auth::check());
    }

    /**
     * Test token refresh rotates refresh token atomically and updates database record.
     */
    public function test_token_refresh_rotates_token_atomically(): void
    {
        $user = User::create([
            'kpfc_sub' => 'user-sub-refresh',
            'name' => 'Refresh Test',
            'email' => 'refresh@example.com',
            'fleet_access' => true,
        ]);

        UserSsoToken::create([
            'user_id' => $user->id,
            'access_token' => 'old_access_token',
            'refresh_token' => 'old_refresh_token',
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            $this->issuer.'/oauth/token' => Http::response([
                'access_token' => 'new_rotated_access_token',
                'refresh_token' => 'new_rotated_refresh_token',
                'token_type' => 'Bearer',
                'expires_in' => 900,
            ], 200),
        ]);

        $service = app(KpfcSsoService::class);
        $refreshed = $service->refreshToken($user);

        $this->assertNotNull($refreshed);
        $this->assertSame('new_rotated_access_token', $refreshed->access_token);
        $this->assertSame('new_rotated_refresh_token', $refreshed->refresh_token);
    }

    /**
     * Test failed token refresh discards tokens.
     */
    public function test_failed_token_refresh_discards_stored_tokens(): void
    {
        $user = User::create([
            'kpfc_sub' => 'user-sub-failed-refresh',
            'name' => 'Failed Refresh Test',
            'email' => 'failed_refresh@example.com',
            'fleet_access' => true,
        ]);

        UserSsoToken::create([
            'user_id' => $user->id,
            'access_token' => 'old_access',
            'refresh_token' => 'invalid_refresh',
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            $this->issuer.'/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token is invalid.',
            ], 400),
        ]);

        $service = app(KpfcSsoService::class);
        $result = $service->refreshToken($user);

        $this->assertNull($result);
        $this->assertDatabaseMissing('user_sso_tokens', ['user_id' => $user->id]);
    }

    /**
     * Test Fleet logout revokes token at identity provider and destroys session.
     */
    public function test_logout_revokes_token_and_destroys_session(): void
    {
        $user = User::create([
            'kpfc_sub' => 'sub-logout-test',
            'name' => 'Logout User',
            'email' => 'logout@example.com',
            'fleet_access' => true,
        ]);

        UserSsoToken::create([
            'user_id' => $user->id,
            'access_token' => 'access_to_revoke',
            'refresh_token' => 'refresh_to_discard',
        ]);

        Http::fake([
            $this->issuer.'/api/oauth/revoke' => Http::response(['revoked' => true], 200),
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());

        // Tokens discarded
        $this->assertDatabaseMissing('user_sso_tokens', ['user_id' => $user->id]);

        // Revoke API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/oauth/revoke') &&
                   $request['token'] === 'access_to_revoke';
        });
    }

    /**
     * Test token introspection via HTTP Basic Auth.
     */
    public function test_token_introspection(): void
    {
        Http::fake([
            $this->issuer.'/api/oauth/introspect' => Http::response([
                'active' => true,
                'client_id' => $this->clientId,
                'sub' => 'sub-intro-test',
                'scope' => 'fleet:login fleet:profile',
                'iat' => time() - 60,
                'exp' => time() + 840,
            ], 200),
        ]);

        $service = app(KpfcSsoService::class);
        $result = $service->introspectToken('valid_access_token');

        $this->assertTrue($result['active']);
        $this->assertSame('sub-intro-test', $result['sub']);
    }

    /**
     * Test lifecycle webhook rejects timestamps older than five minutes.
     */
    public function test_webhook_rejects_expired_timestamp(): void
    {
        $oldTimestamp = time() - 301; // > 5 minutes
        $payload = ['id' => 'evt_1', 'event' => 'user.updated'];
        $rawBody = json_encode($payload);

        $sig = 'v1='.hash_hmac('sha256', $oldTimestamp.'.'.$rawBody, $this->webhookSecret);

        $response = $this->postJson('/auth/kpfc/webhook', $payload, [
            'X-KPFC-Event-Id' => 'evt_1',
            'X-KPFC-Timestamp' => (string) $oldTimestamp,
            'X-KPFC-Signature' => $sig,
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test lifecycle webhook rejects invalid HMAC signature.
     */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $timestamp = time();
        $payload = ['id' => 'evt_2', 'event' => 'user.updated'];

        $response = $this->postJson('/auth/kpfc/webhook', $payload, [
            'X-KPFC-Event-Id' => 'evt_2',
            'X-KPFC-Timestamp' => (string) $timestamp,
            'X-KPFC-Signature' => 'v1=invalidhexhmacsignature000000000000000000000000000000000000000000',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test webhook duplicate event ID is idempotent (returns 200 without second effect).
     */
    public function test_webhook_deduplication_is_idempotent(): void
    {
        $timestamp = time();
        $eventId = '108d5874-751a-4baa-aef4-259acc61ba7d';

        // Pre-insert receipt
        SsoEventReceipt::create([
            'event_id' => $eventId,
            'event_type' => 'user.access_revoked',
        ]);

        $payload = [
            'id' => $eventId,
            'event' => 'user.access_revoked',
            'subject' => 'bca7909f-cdf0-4f59-a69d-6cb61f7d34a6',
        ];

        $rawBody = json_encode($payload);
        $sig = 'v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        $response = $this->postJson('/auth/kpfc/webhook', $payload, [
            'X-KPFC-Event-Id' => $eventId,
            'X-KPFC-Timestamp' => (string) $timestamp,
            'X-KPFC-Signature' => $sig,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'already_processed']);
    }

    /**
     * Test webhook user.disabled and user.access_revoked terminate session and discard tokens.
     */
    public function test_webhook_user_access_revoked_terminates_session_and_discards_tokens(): void
    {
        $sub = 'sub-revoked-test-999';
        $user = User::create([
            'kpfc_sub' => $sub,
            'name' => 'Active User',
            'email' => 'active@example.com',
            'fleet_access' => true,
        ]);

        UserSsoToken::create([
            'user_id' => $user->id,
            'access_token' => 'token_to_discard',
            'refresh_token' => 'refresh_to_discard',
        ]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->insert([
                'id' => 'test_session_id_123',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'payload',
                'last_activity' => time(),
            ]);
        }

        $timestamp = time();
        $eventId = 'event-access-revoked-uuid';
        $payload = [
            'id' => $eventId,
            'event' => 'user.access_revoked',
            'subject' => $sub,
            'occurred_at' => '2026-09-22T09:10:00+03:00',
            'user' => [
                'sub' => $sub,
                'name' => 'Active User',
                'email' => 'active@example.com',
                'fleet_access' => false,
            ],
        ];

        $rawBody = json_encode($payload);
        $sig = 'v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        $response = $this->postJson('/auth/kpfc/webhook', $payload, [
            'X-KPFC-Event-Id' => $eventId,
            'X-KPFC-Timestamp' => (string) $timestamp,
            'X-KPFC-Signature' => $sig,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'processed_revocation']);

        // Assert user access revoked
        $this->assertFalse($user->fresh()->fleet_access);

        // Assert tokens discarded
        $this->assertDatabaseMissing('user_sso_tokens', ['user_id' => $user->id]);

        // Assert sessions terminated
        if (Schema::hasTable('sessions')) {
            $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        }

        // Assert receipt recorded
        $this->assertDatabaseHas('sso_event_receipts', ['event_id' => $eventId]);
    }

    /**
     * Test webhook user.updated updates mutable profile data by sub.
     */
    public function test_webhook_user_updated_upserts_profile_by_sub(): void
    {
        $sub = 'sub-update-test-888';
        $user = User::create([
            'kpfc_sub' => $sub,
            'name' => 'Before Update',
            'email' => 'before@example.com',
            'phone' => '+254700000001',
            'fleet_access' => true,
        ]);

        $timestamp = time();
        $eventId = 'event-user-updated-uuid';
        $payload = [
            'id' => $eventId,
            'event' => 'user.updated',
            'subject' => $sub,
            'occurred_at' => '2026-09-22T09:15:00+03:00',
            'user' => [
                'sub' => $sub,
                'name' => 'After Update',
                'email' => 'after@example.com',
                'phone' => '+254799999999',
                'fleet_access' => true,
            ],
        ];

        $rawBody = json_encode($payload);
        $sig = 'v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        $response = $this->postJson('/auth/kpfc/webhook', $payload, [
            'X-KPFC-Event-Id' => $eventId,
            'X-KPFC-Timestamp' => (string) $timestamp,
            'X-KPFC-Signature' => $sig,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'processed_upsert']);

        $updated = $user->fresh();
        $this->assertSame('After Update', $updated->name);
        $this->assertSame('after@example.com', $updated->email);
        $this->assertSame('+254799999999', $updated->phone);
    }

    /**
     * Test /api/sso/webhook endpoint defined in routes/api.php processes events.
     */
    public function test_api_sso_webhook_route_processes_lifecycle_event(): void
    {
        $sub = 'sub-api-webhook-test';
        $user = User::create([
            'kpfc_sub' => $sub,
            'name' => 'API Webhook User',
            'email' => 'api_webhook@example.com',
            'fleet_access' => true,
        ]);

        $timestamp = time();
        $eventId = 'api-webhook-event-uuid-001';
        $payload = [
            'id' => $eventId,
            'event' => 'user.disabled',
            'subject' => $sub,
            'occurred_at' => '2026-09-22T09:20:00+03:00',
            'user' => [
                'sub' => $sub,
                'name' => 'API Webhook User',
                'email' => 'api_webhook@example.com',
                'fleet_access' => false,
            ],
        ];

        $rawBody = json_encode($payload);
        $sig = 'v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        $response = $this->postJson('/api/sso/webhook', $payload, [
            'X-KPFC-Event-Id' => $eventId,
            'X-KPFC-Timestamp' => (string) $timestamp,
            'X-KPFC-Signature' => $sig,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'processed_revocation']);
        $this->assertFalse($user->fresh()->fleet_access);
    }

    /**
     * Test /api/auth/kpfc/redirect initiates PKCE session.
     */
    public function test_api_auth_kpfc_redirect_initiates_pkce(): void
    {
        $response = $this->get('/api/auth/kpfc/redirect');

        $response->assertStatus(302);
        $this->assertTrue(session()->has('kpfc_sso_state'));
        $this->assertTrue(session()->has('kpfc_code_verifier'));
    }
}
