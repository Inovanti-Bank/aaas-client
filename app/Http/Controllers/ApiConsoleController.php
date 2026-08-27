<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestBodyException;
use App\Exceptions\MissingIaaasCredentialsException;
use App\Http\Requests\SaveIaaasCredentialsRequest;
use App\Http\Requests\SendConsoleRequest;
use App\Services\ConsoleRequestService;
use App\Services\IaaasCredentialsService;
use App\Services\IbaasSessionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ApiConsoleController extends Controller
{
    public function __construct(
        private readonly ConsoleRequestService $consoleRequestService,
        private readonly IbaasSessionService $ibaasSessionService,
        private readonly IaaasCredentialsService $iaaasCredentialsService,
    ) {}

    public function showConsole(Request $request): View
    {
        $iaaasBaseUrl = (string) (config('services.aaas.iaaas.base_url') ?? url('/'));
        $ibaasBaseUrl = (string) (config('services.aaas.ibaas.base_url') ?? url('/'));
        $endpoints = config('aaas_endpoints', []);

        return view('console.index', [
            'initialBaseUrl' => $ibaasBaseUrl,
            'consoleConfig' => [
                'sendUrl' => route('console.send'),
                'iaaasKeysUrl' => route('console.iaaas-keys'),
                'serviceDefaults' => [
                    'iaaas' => ['baseUrl' => $iaaasBaseUrl],
                    'ibaas' => ['baseUrl' => $ibaasBaseUrl],
                ],
                'iaaasGroups' => $endpoints['iaaas_groups'] ?? [],
                'ibaasEndpoints' => array_values(array_unique($endpoints['ibaas_endpoints'] ?? [])),
                'ibaasSession' => $this->ibaasSessionService->getSessionState($request),
                'hasIaaasKeys' => $this->iaaasCredentialsService->hasKeys($request),
            ],
        ]);
    }

    public function send(SendConsoleRequest $request): JsonResponse
    {
        try {
            $result = $this->consoleRequestService->send($request, $request->validated());

            return response()->json($result['payload'], $result['status']);
        } catch (InvalidRequestBodyException|MissingIaaasCredentialsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Console request failed', [
                'endpoint' => $request->validated()['endpoint'] ?? null,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Falha ao processar a requisição. Verifique os logs do servidor para mais detalhes.',
            ], 500);
        }
    }

    public function saveIaaasCredentials(SaveIaaasCredentialsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $privateKey = str_replace(['\r\n', '\n', '\r'], "\n", $data['private_key']);
        $privateKey = preg_replace('/^#\s*/m', '', $privateKey);
        $data['private_key'] = trim($privateKey);

        if (openssl_pkey_get_private($data['private_key']) === false) {
            return response()->json([
                'error' => 'Chave privada inválida. Cole o conteúdo PEM completo, incluindo as linhas BEGIN/END.',
            ], 422);
        }

        $cookies = $this->iaaasCredentialsService->store($request, $data['api_key'], $data['private_key']);

        $response = response()->json(['success' => true]);
        foreach ($cookies as $cookie) {
            $response = $response->cookie($cookie);
        }

        return $response;
    }
}
