<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class IaaasCredentialsService
{
    private const KEY_API = 'iaaas_api_key';

    private const KEY_PRIVATE = 'iaaas_private_key';

    private const COOKIE_LIFETIME_MINUTES = 7 * 24 * 60;

    public function getApiKey(Request $request): string
    {
        return (string) ($request->cookie(self::KEY_API) ?? $request->session()->get(self::KEY_API, ''));
    }

    public function getPrivateKey(Request $request): string
    {
        return (string) ($request->cookie(self::KEY_PRIVATE) ?? $request->session()->get(self::KEY_PRIVATE, ''));
    }

    public function hasKeys(Request $request): bool
    {
        return $this->getApiKey($request) !== '' && $this->getPrivateKey($request) !== '';
    }

    /**
     * Guarda as credenciais na sessão e devolve os cookies (criptografados pelo
     * middleware padrão) que o controller deve anexar à resposta.
     *
     * @return array{0: Cookie, 1: Cookie}
     */
    public function store(Request $request, string $apiKey, string $privateKey): array
    {
        $request->session()->put(self::KEY_API, $apiKey);
        $request->session()->put(self::KEY_PRIVATE, $privateKey);

        return [
            cookie(self::KEY_API, $apiKey, self::COOKIE_LIFETIME_MINUTES),
            cookie(self::KEY_PRIVATE, $privateKey, self::COOKIE_LIFETIME_MINUTES),
        ];
    }
}
