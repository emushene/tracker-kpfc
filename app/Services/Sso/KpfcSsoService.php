<?php

namespace App\Services\Sso;

use App\Models\SsoEventReceipt;
use App\Models\User;
use App\Models\UserSsoToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KpfcSsoService
{
    /**
     * Get the configured OAuth issuer URL without trailing slash.
     */
    public function getIssuer(): string
    {
        return rtrim((string) config('services.kpfc_sso.issuer', 'https://admin-staging.kpfcbuilders.com'), '/');
    }

    /**
     * Get the configured OAuth client ID.
     */
    public function getClientId(): string
    {
        return (string) config('services.kpfc_sso.client_id', '');
    }

    /**
     * Get the configured OAuth client secret.
     */
    public function getClientSecret(): string
    {
        return (string) config('services.kpfc_sso.client_secret', '');
    }

    /**
     * Get the configured OAuth redirect URI.
     */
    public function getRedirectUri(): string
    {
        $redirect = config('services.kpfc_sso.redirect_uri');

        if (! empty($redirect)) {
            return (string) $redirect;
        }

        return route('auth.kpfc.callback');
    }

    /**
     * Get the configured OAuth scopes.
     */
    public function getScopes(): string
    {
        return (string) config('services.kpfc_sso.scopes', 'fleet:login fleet:profile');
    }

    /**
     * Get the configured primary webhook signing secret.
     */
    public function getWebhookSecret(): string
    {
        return (string) config('services.kpfc_sso.webhook_secret', '');
    }

    /**
     * Get the configured secondary (old) webhook signing secret for rotation windows.
     */
    public function getWebhookSecretOld(): ?string
    {
        $old = config('services.kpfc_sso.webhook_secret_old');

        return ! empty($old) ? (string) $old : null;
    }

    /**
     * Generate an authorization redirect URL and save PKCE state/verifier to session.
     *
     * @return array{url: string, state: string, code_verifier: string}
     */
    public function createAuthorizationRequest(Request $request): array
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(64);

        // Derive code_challenge = BASE64URL(SHA256(code_verifier))
        $hash = hash('sha256', $codeVerifier, true);
        $codeChallenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        $request->session()->put('kpfc_sso_state', $state);
        $request->session()->put('kpfc_code_verifier', $codeVerifier);

        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => $this->getScopes(),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        $url = $this->getIssuer().'/oauth/authorize?'.http_build_query($params);

        return [
            'url' => $url,
            'state' => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    /**
     * Handle the OAuth callback with PKCE, retrieve tokens and profile, and upsert local shadow user.
     */
    public function handleCallback(Request $request): User
    {
        if ($request->has('error')) {
            $errorDesc = $request->input('error_description') ?: $request->input('error');
            throw new HttpException(400, "OAuth error: {$errorDesc}");
        }

        $savedState = $request->session()->pull('kpfc_sso_state');
        $codeVerifier = $request->session()->pull('kpfc_code_verifier');
        $state = $request->string('state')->toString();
        $code = $request->string('code')->toString();

        if (empty($savedState) || empty($state) || ! hash_equals($savedState, $state)) {
            throw new HttpException(400, 'Invalid OAuth state parameter');
        }

        if (empty($codeVerifier)) {
            throw new HttpException(400, 'Missing PKCE code verifier');
        }

        if (empty($code)) {
            throw new HttpException(400, 'Missing authorization code');
        }

        // Exchange authorization code for tokens
        $tokenResponse = Http::timeout(15)
            ->asForm()
            ->acceptJson()
            ->post($this->getIssuer().'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'redirect_uri' => $this->getRedirectUri(),
                'code' => $code,
                'code_verifier' => $codeVerifier,
            ]);

        if (! $tokenResponse->successful()) {
            $errorData = $tokenResponse->json() ?? [];
            $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? 'Token exchange failed';
            throw new HttpException(400, "OAuth token exchange error: {$errorMessage}");
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (empty($accessToken)) {
            throw new HttpException(400, 'Access token missing from token response');
        }

        // Fetch user profile from profile contract
        $profileResponse = Http::timeout(15)
            ->withToken($accessToken)
            ->acceptJson()
            ->get($this->getIssuer().'/api/sso/user');

        if ($profileResponse->status() === 403) {
            throw new HttpException(403, 'fleet_access_denied');
        }

        if (! $profileResponse->successful()) {
            throw new HttpException($profileResponse->status(), 'Failed to retrieve user profile from identity provider');
        }

        $profile = $profileResponse->json();
        $sub = $profile['sub'] ?? null;

        if (empty($sub)) {
            throw new HttpException(400, 'Profile missing immutable sub identifier');
        }

        if (empty($profile['fleet_access'])) {
            throw new HttpException(403, 'fleet_access_denied');
        }

        // Wrap local shadow user upsert and encrypted token persistence in one atomic transaction
        return DB::transaction(function () use ($profile, $tokenData): User {
            $user = User::updateOrCreate(
                ['kpfc_sub' => $profile['sub']],
                [
                    'name' => $profile['name'] ?? '',
                    'email' => $profile['email'] ?? null,
                    'phone' => $profile['phone'] ?? null,
                    'role' => $profile['role'] ?? 'user',
                    'fleet_access' => true,
                ]
            );

            $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : 900;

            UserSsoToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'token_type' => $tokenData['token_type'] ?? 'Bearer',
                    'expires_at' => now()->addSeconds($expiresIn),
                ]
            );

            Auth::login($user);

            return $user;
        });
    }

    /**
     * Atomically rotate refresh token and obtain new access token.
     */
    public function refreshToken(User $user): ?UserSsoToken
    {
        return DB::transaction(function () use ($user): ?UserSsoToken {
            $ssoToken = UserSsoToken::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $ssoToken || empty($ssoToken->refresh_token)) {
                return null;
            }

            $response = Http::timeout(15)
                ->asForm()
                ->acceptJson()
                ->post($this->getIssuer().'/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->getClientId(),
                    'client_secret' => $this->getClientSecret(),
                    'refresh_token' => $ssoToken->refresh_token,
                    'scope' => $this->getScopes(),
                ]);

            if (! $response->successful()) {
                // If refresh token is expired or revoked, discard tokens
                $ssoToken->delete();

                return null;
            }

            $data = $response->json();
            $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 900;

            $ssoToken->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $ssoToken->refresh_token,
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_at' => now()->addSeconds($expiresIn),
            ]);

            return $ssoToken;
        });
    }

    /**
     * Revoke access token via HTTP Basic Authentication.
     */
    public function revokeToken(string $accessToken): bool
    {
        $response = Http::timeout(15)
            ->withBasicAuth($this->getClientId(), $this->getClientSecret())
            ->asForm()
            ->acceptJson()
            ->post($this->getIssuer().'/api/oauth/revoke', [
                'token' => $accessToken,
            ]);

        return $response->successful();
    }

    /**
     * Introspect an access token via HTTP Basic Authentication.
     *
     * @return array<string, mixed>
     */
    public function introspectToken(string $accessToken): array
    {
        $response = Http::timeout(15)
            ->withBasicAuth($this->getClientId(), $this->getClientSecret())
            ->asForm()
            ->acceptJson()
            ->post($this->getIssuer().'/api/oauth/introspect', [
                'token' => $accessToken,
            ]);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                return ['active' => false, 'error' => 'invalid_client'];
            }

            return ['active' => false];
        }

        return $response->json() ?? ['active' => false];
    }

    /**
     * Destroy Fleet session and revoke token on logout.
     */
    public function logout(User $user, ?Request $request = null): void
    {
        $ssoToken = UserSsoToken::query()->where('user_id', $user->id)->first();

        if ($ssoToken && ! empty($ssoToken->access_token)) {
            try {
                $this->revokeToken($ssoToken->access_token);
            } catch (\Throwable) {
                // Revocation is idempotent; proceed with session termination even on network failure
            }
        }

        // Discard stored tokens immediately
        UserSsoToken::query()->where('user_id', $user->id)->delete();

        // Destroy local Fleet session
        Auth::logout();

        if ($request && $request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Verify and handle an incoming lifecycle webhook event from KPFC Admin.
     *
     * @return array<string, string>
     */
    public function handleWebhook(Request $request): array
    {
        $eventId = (string) $request->header('X-KPFC-Event-Id');
        $timestamp = (string) $request->header('X-KPFC-Timestamp');
        $signatureHeader = (string) $request->header('X-KPFC-Signature');

        if (empty($eventId) || empty($timestamp) || empty($signatureHeader)) {
            throw new HttpException(400, 'Missing required webhook headers');
        }

        // 1. Reject timestamps more than five minutes from local clock
        $timeDiff = abs(time() - (int) $timestamp);
        if ($timeDiff > 300) {
            throw new HttpException(400, 'Webhook timestamp exceeds 5-minute clock skew limit');
        }

        // 2 & 3. Verify HMAC-SHA256 signature
        if (! str_starts_with($signatureHeader, 'v1=')) {
            throw new HttpException(401, 'Invalid signature format');
        }

        $providedSig = substr($signatureHeader, 3);
        $rawBody = $request->getContent();
        $payloadToSign = $timestamp.'.'.$rawBody;

        $secret = $this->getWebhookSecret();
        $expectedSig = hash_hmac('sha256', $payloadToSign, $secret);

        $valid = hash_equals($expectedSig, $providedSig);

        // Allow rotation window check if old secret is configured
        if (! $valid && ($oldSecret = $this->getWebhookSecretOld()) !== null) {
            $expectedOldSig = hash_hmac('sha256', $payloadToSign, $oldSecret);
            $valid = hash_equals($expectedOldSig, $providedSig);
        }

        if (! $valid) {
            throw new HttpException(401, 'Invalid webhook signature');
        }

        $payload = $request->json()->all();

        // 4. Insert X-KPFC-Event-Id into a unique event-receipt table before applying event
        if (SsoEventReceipt::query()->where('event_id', $eventId)->exists()) {
            return ['status' => 'already_processed'];
        }

        try {
            SsoEventReceipt::create([
                'event_id' => $eventId,
                'event_type' => $payload['event'] ?? null,
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
            // Already processed due to concurrent delivery race
            return ['status' => 'already_processed'];
        }

        $event = $payload['event'] ?? '';
        $subject = $payload['subject'] ?? ($payload['user']['sub'] ?? null);

        if (empty($subject)) {
            return ['status' => 'processed_no_subject'];
        }

        // 5. Terminate every Fleet session and discard stored tokens for user.disabled and user.access_revoked
        if (in_array($event, ['user.disabled', 'user.access_revoked'], true)) {
            $user = User::query()->where('kpfc_sub', $subject)->first();

            if ($user) {
                $user->update(['fleet_access' => false]);

                // Terminate all sessions for this user if sessions table exists
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                // Discard stored tokens immediately
                UserSsoToken::query()->where('user_id', $user->id)->delete();
            }

            return ['status' => 'processed_revocation'];
        }

        // 6. Upsert mutable profile data by subject for update/restore events
        if (in_array($event, ['user.updated', 'user.restored'], true)) {
            $userData = $payload['user'] ?? [];
            $fleetAccess = isset($userData['fleet_access']) ? (bool) $userData['fleet_access'] : true;

            User::updateOrCreate(
                ['kpfc_sub' => $subject],
                [
                    'name' => $userData['name'] ?? '',
                    'email' => $userData['email'] ?? null,
                    'phone' => $userData['phone'] ?? null,
                    'fleet_access' => $fleetAccess,
                ]
            );

            return ['status' => 'processed_upsert'];
        }

        return ['status' => 'processed_unknown_event'];
    }
}
