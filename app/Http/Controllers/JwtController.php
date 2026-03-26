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

        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
        if ($queryParams !== '') {
            $qp = ltrim($queryParams, '?');
            $fullUrl .= '?' . $qp;
        }

        try {
            $privateKey = (new GenerateSignedJwt())->resolvePrivateKey();
            $token = (new GenerateSignedJwt())->call($privateKey, $apiKey, $endpoint, $method, $body);

            $headers = [
                'X-auth-token' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ];
            if ($service === 'iaaas' && $apiKey !== '') {
                $headers['X-api-key'] = $apiKey;
            }

            $options = [];
            if ($body !== null) {
                $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $headers['Content-Type'] = 'application/json';
            }

            $response = Http::withHeaders($headers)->send($method, $fullUrl, $options);

            $raw = $response->body();
            $decodedResponse = json_decode($raw, true);
            $bodyResponse = json_last_error() === JSON_ERROR_NONE ? $decodedResponse : $raw;

            return response()->json([
                'status' => $response->status(),
                'ok' => $response->successful(),
                'headers' => $response->headers(),
                'body' => $bodyResponse,
                'raw' => $raw,
                'token' => $token,
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
        return (string) (config('services.aaas.iaaas.base_url') ?? url('/'));
    }

    private function resolveApiKey(string $service): string
    {
        return $service === 'iaaas'
            ? (string) (config('services.aaas.iaaas.api_key') ?? '')
            : '';
    }
}
