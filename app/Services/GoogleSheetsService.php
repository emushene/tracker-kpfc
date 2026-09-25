<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSheetsService
{
    private string $spreadsheetId;

    private string $credentialsPath;

    public function __construct()
    {
        $this->spreadsheetId = config('services.google.sheets.spreadsheet_id');
        $this->credentialsPath = config('services.google.sheets.credentials');

        if (! $this->spreadsheetId) {
            throw new RuntimeException('Google Spreadsheet ID is not configured.');
        }

        if (! file_exists($this->credentialsPath)) {
            throw new RuntimeException(
                "Google credentials file not found: {$this->credentialsPath}"
            );
        }
    }

    private function getAccessToken(): string
    {
        $credentials = json_decode(
            file_get_contents($this->credentialsPath),
            true
        );

        if (! $credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Invalid Google service account JSON.');
        }

        $now = time();

        $header = $this->base64UrlEncode(
            json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ])
        );

        $claim = $this->base64UrlEncode(
            json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ])
        );

        $unsignedJwt = $header.'.'.$claim;

        $signature = '';
        openssl_sign(
            $unsignedJwt,
            $signature,
            $credentials['private_key'],
            OPENSSL_ALGO_SHA256
        );

        $jwt = $unsignedJwt.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Google authentication failed: '.$response->body()
            );
        }

        return $response->json('access_token');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(
            strtr(base64_encode($data), '+/', '-_'),
            '='
        );
    }

    public function readVehicles(): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->get(
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/Protrack365!A:F"
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Google Sheets read failed: '.$response->body()
            );
        }

        return $response->json('values', []);
    }

    public function updateLocation(int $row, string $location): void
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->put(
                "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/Protrack365!F{$row}?valueInputOption=USER_ENTERED",
                [
                    'range' => "Protrack365!F{$row}",
                    'majorDimension' => 'ROWS',
                    'values' => [
                        [$location],
                    ],
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Google Sheets update failed: '.$response->body()
            );
        }
    }
}
