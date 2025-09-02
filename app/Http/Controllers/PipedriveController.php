<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PipedriveController extends Controller
{
    public function show(Request $request)
    {
        $personId = $request->query('selectedIds');
        $jwtToken = $request->query('token');

        if (!$personId || !$jwtToken) {
            return response("Missing required parameters", 400);
        }

        $pipedriveApiToken = env('PIPEDRIVE_API_TOKEN');

        // Call Pipedrive API using real API token
        $response = Http::get("https://api.pipedrive.com/v1/persons/{$personId}", [
            'api_token' => $pipedriveApiToken,
        ]);

        if ($response->failed()) {
            return response("Error retrieving person data", 500);
        }

        $personData = $response->json();
        $email = $personData['data']['email'][0]['value'] ?? null;

        if (!$email) {
            return response("Email not found for the selected person", 404);
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
            ])
            ->header('Content-Type', 'text/html')
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors 'self' https://*.pipedrive.com");
    }

    public function handleCallback(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return response()->json(['error' => 'Authorization code missing'], 400);
        }

        $response = Http::asForm()->post('https://oauth.pipedrive.com/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => env('PIPEDRIVE_REDIRECT_URI'),
            'client_id'     => env('PIPEDRIVE_CLIENT_ID'),
            'client_secret' => env('PIPEDRIVE_CLIENT_SECRET'),
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to get access token', 'details' => $response->json()], 500);
        }

        $data = $response->json();

        return redirect('/pipedrive/success')->with('message', 'Pipedrive connected successfully!');
    }
}