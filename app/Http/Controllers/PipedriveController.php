<?php

namespace App\Http\Controllers;

use App\Models\PipedriveConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PipedriveController extends Controller
{
    // 1) Redirect user to install (share this)
    public function connect(Request $request)
    {
        $state = Str::random(40);
        $query = http_build_query([
            'client_id'    => env('PIPEDRIVE_CLIENT_ID'),
            'redirect_uri' => env('PIPEDRIVE_REDIRECT_URI'),
            'state'        => $state,
            'scope'        => 'persons:read',
        ]);

        return redirect('https://oauth.pipedrive.com/oauth/authorize?' . $query);
    }

    public function handleCallback(Request $request)
    {
        $code = $request->query('code');
        if (!$code) {
            return response()->json(['error' => 'Authorization code missing'], 400);
        }

        // 1) Exchange code for token
        $tokenRes = Http::asForm()->post('https://oauth.pipedrive.com/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => env('PIPEDRIVE_REDIRECT_URI'),
            'client_id'     => env('PIPEDRIVE_CLIENT_ID'),
            'client_secret' => env('PIPEDRIVE_CLIENT_SECRET'),
        ]);

        if ($tokenRes->failed()) {
            Log::error('Pipedrive token exchange failed', [
                'body' => $tokenRes->json()
            ]);
            return response()->json(['error' => 'Failed to get tokens', 'details' => $tokenRes->json()], 500);
        }

        $j = $tokenRes->json();
        $accessToken  = $j['access_token'];
        $refreshToken = $j['refresh_token'] ?? null;
        $expiresIn    = (int)($j['expires_in'] ?? 3600);
        $apiDomain    = rtrim($j['api_domain'] ?? 'https://api.pipedrive.com', '/');

        $who = Http::withToken($accessToken)->get($apiDomain . '/v1/users/me');
        $whoData = $who->ok() ? $who->json('data') : null;

        $companyId = $whoData['company_id']['value'] ?? $whoData['company_id'] ?? null;
        $pipedriveUserId = $whoData['id'] ?? null;

        Log::info('OAuth callback data', [
            'company_id' => $companyId,
            'api_domain' => $apiDomain,
            'user_id'    => $pipedriveUserId,
            'access_token' => substr($accessToken, 0, 12) . '...', // don’t log full
        ]);

        PipedriveConnection::updateOrCreate(
            [
                'company_id' => $companyId,
                'api_domain' => $apiDomain,
            ],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'access_token_expires_at' => now()->addSeconds($expiresIn),
                'pipedrive_user_id' => $pipedriveUserId,
            ]
        );

        return redirect('/pipedrive/success')
            ->with('message', 'Pipedrive connected successfully!');
    }

    public function success()
    {
        return 'Pipedrive app installed successfully — you can close this window.';
    }

    public function entry(Request $request)
    {
        $payload = $request->attributes->get('pipedrive_panel_payload', []);
        $selectedIds = $request->query('selectedIds', '');

        $apiDomain = rtrim($payload['api_domain'] ?? '', '/');
        $companyId = $payload['companyId'] ?? null;

        $conn = PipedriveConnection::where('company_id', $companyId)
            ->where('api_domain', $apiDomain)
            ->first();

        if (!$conn) {
            $connectUrl = url('/pipedrive/connect');
            return response()
                ->view('pipedrive-connect-needed', ['connectUrl' => $connectUrl])
                ->header('X-Frame-Options', 'ALLOWALL')
                ->header('Content-Security-Policy', "frame-ancestors 'self' https://*.pipedrive.com");
        }

        if ($conn->isExpired()) {
            $this->refreshAccessToken($conn);
        }

        $apiBase = $conn->apiBase();
        $pid = trim(explode(',', (string)$selectedIds)[0]);

        $personRes = Http::withToken($conn->access_token)->get("{$apiBase}/persons/{$pid}");
        if ($personRes->failed()) {
            return response("Error retrieving person data", 500);
        }

        $person = $personRes->json('data') ?? [];

        $email = null;
        if (!empty($person['email'][0]['value'])) {
            $email = $person['email'][0]['value'];
        }

        if (!$email) {
            return response("Email not found for person", 404);
        }

        $internalResponse = Http::get('https://octopus-app-3hac5.ondigitalocean.app/api/stripe_data', [
            'email' => $email,
        ]);

        if ($internalResponse->failed()) {
            return response("Error retrieving internal data", 500);
        }

        $data = $internalResponse->json();

        return response()
            ->view('pipedrive', [
                'data' => $data,
                'email' => $email,
                'person' => $person,
            ])
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors 'self' https://*.pipedrive.com");
    }

    protected function refreshAccessToken(PipedriveConnection $conn)
    {
        if (!$conn->refresh_token) {
            throw new \RuntimeException('No refresh token available');
        }

        $res = Http::asForm()->post('https://oauth.pipedrive.com/oauth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $conn->refresh_token,
            'client_id'     => env('PIPEDRIVE_CLIENT_ID'),
            'client_secret' => env('PIPEDRIVE_CLIENT_SECRET'),
        ]);

        if ($res->failed()) {
            throw new \RuntimeException('Failed to refresh token: ' . json_encode($res->json()));
        }

        $j = $res->json();
        $conn->update([
            'access_token' => $j['access_token'],
            'refresh_token' => $j['refresh_token'] ?? $conn->refresh_token,
            'access_token_expires_at' => now()->addSeconds((int)($j['expires_in'] ?? 3600)),
            'api_domain' => $j['api_domain'] ?? $conn->api_domain,
        ]);

        return $conn->fresh();
    }
}
