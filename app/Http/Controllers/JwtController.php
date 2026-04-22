<?php

namespace App\Http\Controllers;

use App\Utils\GenerateSignedJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Exception;

class JwtController extends Controller
{
    public function showConsole()
    {
        $baseUrl = (string) (config('services.aaas.iaaas.base_url') ?? url('/'));

        return view('jwt.console', [
            'baseUrl' => $baseUrl,
            'endpoints' => config('aaas_endpoints', []),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'method' => 'sometimes|string',
            'query_params' => 'nullable|string',
            'body' => 'nullable|string',
            'base_url' => 'sometimes|string',
            'service' => 'sometimes|string|in:iaaas,ibaas',
        ]);

        $method = strtoupper($data['method'] ?? 'GET');
        $endpoint = $data['endpoint'];
        $normalizedEndpoint = '/' . ltrim($endpoint, '/');
        $queryParams = trim($data['query_params'] ?? '');
        $service = $data['service'] ?? 'iaaas';
        $baseUrl = $this->resolveBaseUrl($service, $data['base_url'] ?? null);
        $apiKey = $this->resolveApiKey($service);

        $body = null;
        if (isset($data['body']) && $data['body'] !== '') {
            $decoded = json_decode($data['body'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid JSON body: ' . json_last_error_msg()], 422);
            }
            $body = $decoded;
        }

        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($normalizedEndpoint, '/');
        if ($queryParams !== '') {
            $qp = ltrim($queryParams, '?');
            $fullUrl .= '?' . $qp;
        }

        try {
            $headers = ['Accept' => 'application/json'];
            $responseToken = null;

            if ($service === 'iaaas') {
                $privateKey = (new GenerateSignedJwt())->resolvePrivateKey();
                $responseToken = (new GenerateSignedJwt())->call($privateKey, $apiKey, $endpoint, $method, $body);
                $headers['X-auth-token'] = 'Bearer ' . $responseToken;
                if ($apiKey !== '') {
                    $headers['X-api-key'] = $apiKey;
                }
            } else {
                $sessionToken = (string) $request->session()->get('ibaas.token', '');
                $isAuthLogin = in_array($normalizedEndpoint, ['/v1/auth/login', '/v1/auth/login-webhook'], true);
                if ($sessionToken !== '' && (!$this->isIbaasAuthEndpoint($normalizedEndpoint) || $normalizedEndpoint === '/v1/auth/logout') && !$isAuthLogin) {
                    $headers['Authorization'] = 'Bearer ' . $sessionToken;
                }
            }

            $options = [];
            if ($service === 'ibaas' && $normalizedEndpoint === '/v1/auth/refresh' && $body === null) {
                $savedRefreshToken = (string) $request->session()->get('ibaas.refresh_token', '');
                if ($savedRefreshToken !== '') {
                    $body = ['refresh_token' => $savedRefreshToken];
                }
            }
            if ($service === 'ibaas' && $normalizedEndpoint === '/v1/auth/login-2fa') {
                $savedTwoFactorId = (string) $request->session()->get('ibaas.two_factor_id', '');
                if ($savedTwoFactorId !== '') {
                    $body = is_array($body) ? $body : [];
                    if (!isset($body['two_factor_id']) || !is_string($body['two_factor_id']) || trim($body['two_factor_id']) === '') {
                        $body['two_factor_id'] = $savedTwoFactorId;
                    }
                }
            }
            if ($body !== null) {
                $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $headers['Content-Type'] = 'application/json';
            }

            $response = Http::withHeaders($headers)->send($method, $fullUrl, $options);

            $raw = $response->body();
            $decodedResponse = json_decode($raw, true);
            $bodyResponse = json_last_error() === JSON_ERROR_NONE ? $decodedResponse : $raw;

            if ($service === 'ibaas') {
                $this->syncIbaasSessionTokens($request, $normalizedEndpoint, $bodyResponse, $response->successful());
            }

            return response()->json([
                'status' => $response->status(),
                'ok' => $response->successful(),
                'headers' => $response->headers(),
                'body' => $bodyResponse,
                'raw' => $raw,
                'token' => $responseToken,
                'ibaas_session' => [
                    'has_token' => (string) $request->session()->get('ibaas.token', '') !== '',
                    'has_refresh_token' => (string) $request->session()->get('ibaas.refresh_token', '') !== '',
                    'has_two_factor_id' => (string) $request->session()->get('ibaas.two_factor_id', '') !== '',
                ],
            ], $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function resolveBaseUrl(string $service, ?string $customBaseUrl): string
    {
        if (!empty($customBaseUrl)) {
            return $customBaseUrl;
        }
        if ($service === 'iaaas') {
            return (string) (config('services.aaas.iaaas.base_url') ?? url('/'));
        }

        return (string) (config('services.aaas.ibaas.base_url') ?? url('/'));
    }

    private function resolveApiKey(string $service): string
    {
        return $service === 'iaaas'
            ? (string) (config('services.aaas.iaaas.api_key') ?? '')
            : '';
    }

    private function syncIbaasSessionTokens(Request $request, string $endpoint, mixed $bodyResponse, bool $ok): void
    {
        if ($endpoint === '/v1/auth/logout' && $ok) {
            $request->session()->forget(['ibaas.token', 'ibaas.refresh_token', 'ibaas.two_factor_id']);
            return;
        }

        if (!is_array($bodyResponse)) {
            return;
        }

        $twoFactorRequired = (bool) data_get($bodyResponse, 'two_factor_required', false);
        $twoFactorId = data_get($bodyResponse, 'two_factor_id');
        if ($twoFactorRequired && is_string($twoFactorId) && $twoFactorId !== '') {
            $request->session()->put('ibaas.two_factor_id', $twoFactorId);
        }

        $token = data_get($bodyResponse, 'authorization.token');
        $refreshToken = data_get($bodyResponse, 'authorization.refresh_token');

        if (is_string($token) && $token !== '') {
            $request->session()->put('ibaas.token', $token);
            $request->session()->forget('ibaas.two_factor_id');
        }
        if (is_string($refreshToken) && $refreshToken !== '') {
            $request->session()->put('ibaas.refresh_token', $refreshToken);
        }
    }

    private function isIbaasAuthEndpoint(string $endpoint): bool
    {
        return str_starts_with($endpoint, '/v1/auth/');
    }
}
