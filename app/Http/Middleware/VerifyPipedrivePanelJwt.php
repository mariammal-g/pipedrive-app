<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyPipedrivePanelJwt
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->query('token');

        if (!$token) {
            return response('Missing panel token', 401);
        }
        try {
            [$headerB64] = explode('.', $token);
            $header = json_decode(base64_decode(strtr($headerB64, '-_', '+/')), true);

            if (!$header || empty($header['alg'])) {
                throw new \Exception('Invalid JWT header');
            }

            $alg = $header['alg'];
            if ($alg === 'HS256') {
                $clientSecret = trim(env('PIPEDRIVE_CLIENT_SECRET'));
                if (!$clientSecret) {
                    throw new \Exception('Missing PIPEDRIVE_CLIENT_SECRET');
                }
                $decoded = (array) JWT::decode($token, new Key($clientSecret, 'HS256'));

                if (!empty($decoded['companyId'])) {
                    $conn = \App\Models\PipedriveConnection::where('company_id', $decoded['companyId'])->first();
                    if ($conn && $conn->api_domain) {
                        $decoded['api_domain'] = $conn->api_domain;
                    }
                }
            } elseif ($alg === 'RS256') {

                $companyId = $request->query('companyId');
                if (!$companyId) {
                    throw new \Exception('Missing companyId for RS256 verification');
                }

                $jwksUrl = "https://{$companyId}.pipedrive.com/.well-known/jwks.json";
                $jwks = Http::get($jwksUrl)->json();

                if (empty($jwks['keys'])) {
                    throw new \Exception("Unable to fetch JWKS from {$jwksUrl}");
                }

                $decoded = $this->decodeRs256Token($token, $jwks);
                Log::info('Installed RS256 panel token', $decoded);
            } else {
                throw new \Exception("Unsupported JWT alg: {$alg}");
            }

            $request->attributes->set('pipedrive_panel_payload', $decoded);
        } catch (\Throwable $e) {
            Log::error('JWT error', [
                'error' => $e->getMessage(),
                'token' => $token,
                'keyLength' => strlen($clientSecret),
            ]);
            return response('Invalid app token middleware: ' . $e->getMessage(), 401);
        }

        return $next($request);
    }

    private function decodeRs256Token(string $token, array $jwks): array
    {
        [$headerB64] = explode('.', $token);
        $header = json_decode(base64_decode(strtr($headerB64, '-_', '+/')), true);

        if (empty($header['kid'])) {
            throw new \Exception('RS256 token missing kid');
        }

        $pem = null;
        foreach ($jwks['keys'] as $jwk) {
            if ($jwk['kid'] === $header['kid']) {
                $pem = $this->jwkToPem($jwk);
                break;
            }
        }

        if (!$pem) {
            throw new \Exception('No matching JWKS key found');
        }

        JWT::$leeway = 120;
        return (array) JWT::decode($token, new Key($pem, 'RS256'));
    }

    private function jwkToPem(array $jwk): string
    {
        $n = $this->urlsafeB64Decode($jwk['n']);
        $e = $this->urlsafeB64Decode($jwk['e']);

        $modulus = pack('Ca*a*', 0x02, $this->encodeLength(strlen($n)), $n);
        $pubExp = pack('Ca*a*', 0x02, $this->encodeLength(strlen($e)), $e);

        $sequence = pack('Ca*a*a*', 0x30, $this->encodeLength(strlen($modulus . $pubExp)), $modulus, $pubExp);
        $rsaOID = hex2bin('300d06092a864886f70d0101010500');
        $rsaPubKeyBitString = pack('CCa*', 0x03, strlen($sequence) + 1, "\0" . $sequence);

        $finalSequence = pack('Ca*a*a*', 0x30, $this->encodeLength(strlen($rsaOID . $rsaPubKeyBitString)), $rsaOID, $rsaPubKeyBitString);

        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($finalSequence), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
    }

    private function urlsafeB64Decode(string $input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) $input .= str_repeat('=', 4 - $remainder);
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private function encodeLength(int $length): string
    {
        if ($length <= 0x7F) return chr($length);
        $temp = ltrim(pack('N', $length), chr(0));
        return chr(0x80 | strlen($temp)) . $temp;
    }
}
