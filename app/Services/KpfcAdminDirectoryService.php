<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KpfcAdminDirectoryService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('kpfc.admin.url');
    }

    /**
     * Get Branches Directory
     */
    public function getBranches(bool $includeInactive = false): array
    {
        $token = $this->getToken('branches', 'fleet:branches');

        $response = $this->client($token)
            ->get('/api/v1/fleet/shops', [
                'include_inactive' => $includeInactive ? 'true' : 'false',
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch branches directory: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get Suppliers Directory
     */
    public function getSuppliers(bool $includeInactive = false): array
    {
        $token = $this->getToken('suppliers', 'fleet:suppliers');

        $response = $this->client($token)
            ->get('/api/v1/fleet/suppliers', [
                'include_inactive' => $includeInactive ? 'true' : 'false',
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch suppliers directory: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Build the HTTP client with retries and auth
     */
    private function client(string $token): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->retry(3, 100, function (Exception $exception, PendingRequest $request) {
                if ($exception instanceof RequestException) {
                    return $exception->response->status() === 429 || $exception->response->serverError();
                }

                return false;
            });
    }

    /**
     * Request or retrieve cached OAuth token for a specific directory
     */
    private function getToken(string $directory, string $scope): string
    {
        $cacheKey = "kpfc_admin_{$directory}_token";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $clientId = config("kpfc.admin.{$directory}.client_id");
        $clientSecret = config("kpfc.admin.{$directory}.client_secret");

        if (! $clientId || ! $clientSecret) {
            throw new Exception("Missing client credentials for KPFC {$directory} API.");
        }

        $response = Http::baseUrl($this->baseUrl)
            ->asForm()
            ->acceptJson()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('/oauth/token', [
                'grant_type' => 'client_credentials',
                'scope' => $scope,
            ]);

        if ($response->failed()) {
            throw new Exception("Failed to obtain OAuth token for {$directory}: ".$response->body());
        }

        $data = $response->json();

        // Cache until shortly before it expires (buffer of 60 seconds)
        $expiresInSeconds = max(0, ($data['expires_in'] ?? 900) - 60);

        Cache::put($cacheKey, $data['access_token'], now()->addSeconds($expiresInSeconds));

        return $data['access_token'];
    }
}
