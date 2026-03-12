<?php

namespace App\Http\Controllers;

use App\Utils\GenerateSignedJwt;
use Illuminate\Http\JsonResponse;
use Exception;

class JwtController extends Controller
{
    public function showConsole()
    {
        $baseUrl = env('BASE_URL', url('/'));

        return view('jwt.console', ['baseUrl' => $baseUrl]);
    }

    public function send(\Illuminate\Http\Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'method' => 'sometimes|string',
            'query_params' => 'nullable|string',
            'body' => 'nullable|string',
            'base_url' => 'sometimes|string',
        ]);

        $method = strtoupper($data['method'] ?? 'GET');
        $endpoint = $data['endpoint'];
        $queryParams = trim($data['query_params'] ?? '');
        $baseUrl = $data['base_url'] ?? env('BASE_URL', url('/'));

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
            $token = (new GenerateSignedJwt())->call($privateKey, env('API_KEY'), $endpoint, $method, $body);

            $headers = [
                'X-api-key' => env('API_KEY'),
                'X-auth-token' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ];

            $options = [];
            if ($body !== null) {
                $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $headers['Content-Type'] = 'application/json';
            }

            $response = \Illuminate\Support\Facades\Http::withHeaders($headers)->send($method, $fullUrl, $options);

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
}
