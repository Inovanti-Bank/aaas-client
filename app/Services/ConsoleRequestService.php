<?php

namespace App\Services;

use App\Exceptions\InvalidRequestBodyException;
use App\Exceptions\MissingIaaasCredentialsException;
use App\Utils\GenerateSignedJwt;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ConsoleRequestService
{
    public const SERVICE_IAAAS = 'iaaas';

    public const SERVICE_IBAAS = 'ibaas';

    private const PDF_ENDPOINT_SUFFIXES = ['/get-payment-slip/pdf'];

    public function __construct(
        private readonly IbaasSessionService $ibaasSession,
        private readonly IaaasCredentialsService $iaaasCredentials,
        private readonly GenerateSignedJwt $jwtGenerator,
    ) {}

    /**
     * Envia a requisição ao serviço escolhido e devolve o payload de resposta do console.
     *
     * @return array{status: int, payload: array<string, mixed>}
     *
     * @throws InvalidRequestBodyException
     */
    public function send(Request $request, array $data): array
    {
        $service = $data['service'] ?? self::SERVICE_IAAAS;
        $method = strtoupper($data['method'] ?? 'GET');
        $endpoint = $data['endpoint'];
        $normalizedEndpoint = '/'.ltrim($endpoint, '/');
        $baseUrl = $this->resolveBaseUrl($service, $data['base_url'] ?? null);
        $apiKey = $this->resolveApiKey($request, $service);
        $fullUrl = $this->buildFullUrl($baseUrl, $normalizedEndpoint, $data['query_params'] ?? '');

        $body = $this->decodeJsonBody($data['body'] ?? null);
        if ($service === self::SERVICE_IBAAS) {
            $body = $this->ibaasSession->prefillAuthBody($request, $normalizedEndpoint, $body);
        }

        $responseToken = null;
        $headers = $this->buildHeaders($request, $service, $normalizedEndpoint, $endpoint, $method, $fullUrl, $apiKey, $body, $responseToken);

        $options = [];
        if ($body !== null) {
            $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        }

        $sentRequest = [
            'service' => $service,
            'method' => $method,
            'url' => $fullUrl,
            'endpoint' => $normalizedEndpoint,
            'headers' => $headers,
            'body' => $body,
            'options' => $options,
        ];

        $response = Http::withHeaders($headers)->send($method, $fullUrl, $options);

        if ($response->status() === 401 && $service === self::SERVICE_IBAAS) {
            $response = $this->retryWithRefreshedToken($request, $response, $baseUrl, $normalizedEndpoint, $method, $fullUrl, $options, $headers, $sentRequest);
        }

        return $this->shapeResponse($request, $service, $normalizedEndpoint, $response, $sentRequest, $responseToken);
    }

    /**
     * @throws InvalidRequestBodyException
     */
    private function decodeJsonBody(?string $rawBody): ?array
    {
        if ($rawBody === null || $rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidRequestBodyException('Invalid JSON body: '.json_last_error_msg());
        }

        return $decoded;
    }

    private function resolveBaseUrl(string $service, ?string $customBaseUrl): string
    {
        if (! empty($customBaseUrl)) {
            return $customBaseUrl;
        }

        $configKey = $service === self::SERVICE_IAAAS
            ? 'services.aaas.iaaas.base_url'
            : 'services.aaas.ibaas.base_url';

        return (string) (config($configKey) ?? url('/'));
    }

    private function resolveApiKey(Request $request, string $service): string
    {
        return $service === self::SERVICE_IAAAS
            ? $this->iaaasCredentials->getApiKey($request)
            : '';
    }

    private function buildFullUrl(string $baseUrl, string $normalizedEndpoint, string $queryParams): string
    {
        $fullUrl = rtrim($baseUrl, '/').'/'.ltrim($normalizedEndpoint, '/');

        $queryParams = trim($queryParams);
        if ($queryParams !== '') {
            $fullUrl .= '?'.ltrim($queryParams, '?');
        }

        return $fullUrl;
    }

    private function buildHeaders(
        Request $request,
        string $service,
        string $normalizedEndpoint,
        string $endpoint,
        string $method,
        string $fullUrl,
        string $apiKey,
        ?array $body,
        ?string &$responseToken,
    ): array {
        $headers = ['Accept' => 'application/json'];

        if (Str::endsWith($normalizedEndpoint, self::PDF_ENDPOINT_SUFFIXES)) {
            $headers['Accept'] = 'application/pdf';
        }

        if (str_contains($fullUrl, 'localhost') || str_contains($fullUrl, '127.0.0.1')) {
            $localTenantDomain = (string) config('services.aaas.local_tenant_domain');
            if ($localTenantDomain !== '') {
                $headers['Host'] = $localTenantDomain;
            }
        }

        if ($service === self::SERVICE_IAAAS) {
            $privateKey = $this->iaaasCredentials->getPrivateKey($request);
            if ($privateKey === '') {
                throw new MissingIaaasCredentialsException(
                    'Credenciais do IAaas não configuradas. Use o botão "Configurar chaves" para informar a API Key e a chave privada.'
                );
            }
            $responseToken = $this->jwtGenerator->call($privateKey, $apiKey, $endpoint, $method, $body);
            $headers['X-auth-token'] = 'Bearer '.$responseToken;
            if ($apiKey !== '') {
                $headers['X-api-key'] = $apiKey;
            }

            return $headers;
        }

        if ($this->ibaasSession->shouldAttachToken($request, $normalizedEndpoint)) {
            $headers['Authorization'] = 'Bearer '.$this->ibaasSession->getToken($request);
        }

        return $headers;
    }

    /**
     * Em 401 no IBaas, tenta renovar o token com o refresh_token salvo e reenvia a requisição original.
     */
    private function retryWithRefreshedToken(
        Request $request,
        Response $originalResponse,
        string $baseUrl,
        string $normalizedEndpoint,
        string $method,
        string $fullUrl,
        array $options,
        array $headers,
        array &$sentRequest,
    ): Response {
        $savedRefreshToken = $this->ibaasSession->getRefreshToken($request);
        if ($savedRefreshToken === '' || $normalizedEndpoint === IbaasSessionService::ENDPOINT_REFRESH) {
            return $originalResponse;
        }

        $refreshUrl = rtrim($baseUrl, '/').IbaasSessionService::ENDPOINT_REFRESH;
        $refreshHeaders = array_intersect_key($headers, array_flip(['Authorization', 'Host']));
        $refreshHeaders['Accept'] = 'application/json';

        $refreshResponse = Http::withHeaders($refreshHeaders)->post($refreshUrl, [
            'refresh_token' => $savedRefreshToken,
        ]);

        if (! $refreshResponse->successful()) {
            $this->ibaasSession->forgetSession($request);

            return $originalResponse;
        }

        $newBodyResponse = $refreshResponse->json();
        $this->ibaasSession->syncTokens($request, IbaasSessionService::ENDPOINT_REFRESH, $newBodyResponse, true);

        $newToken = data_get($newBodyResponse, 'authorization.token');
        if (! is_string($newToken) || $newToken === '') {
            return $originalResponse;
        }

        $headers['Authorization'] = 'Bearer '.$newToken;
        $sentRequest['headers'] = $headers;

        return Http::withHeaders($headers)->send($method, $fullUrl, $options);
    }

    /**
     * @return array{status: int, payload: array<string, mixed>}
     */
    private function shapeResponse(
        Request $request,
        string $service,
        string $normalizedEndpoint,
        Response $response,
        array $sentRequest,
        ?string $responseToken,
    ): array {
        $raw = $response->body();
        $decodedResponse = json_decode($raw, true);
        $bodyResponse = json_last_error() === JSON_ERROR_NONE ? $decodedResponse : $raw;

        if (is_string($raw) && ! mb_check_encoding($raw, 'UTF-8')) {
            $bodyResponse = base64_encode($raw);
            $raw = base64_encode($raw);
        }

        if ($service === self::SERVICE_IBAAS) {
            $this->ibaasSession->syncTokens($request, $normalizedEndpoint, $bodyResponse, $response->successful());
        }

        return [
            'status' => $response->status(),
            'payload' => [
                'status' => $response->status(),
                'ok' => $response->successful(),
                'request' => $sentRequest,
                'headers' => $response->headers(),
                'body' => $bodyResponse,
                'raw' => $raw,
                'token' => $responseToken,
                'ibaas_session' => $this->ibaasSession->getSessionState($request),
            ],
        ];
    }
}
