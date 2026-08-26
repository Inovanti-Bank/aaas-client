<?php

namespace App\Services;

use Illuminate\Http\Request;

class IbaasSessionService
{
    public const ENDPOINT_LOGIN = '/v1/auth/login';

    public const ENDPOINT_LOGIN_WEBHOOK = '/v1/auth/login-webhook';

    public const ENDPOINT_LOGIN_2FA = '/v1/auth/login-2fa';

    public const ENDPOINT_REFRESH = '/v1/auth/refresh';

    public const ENDPOINT_LOGOUT = '/v1/auth/logout';

    private const SESSION_TOKEN = 'ibaas.token';

    private const SESSION_REFRESH_TOKEN = 'ibaas.refresh_token';

    private const SESSION_TWO_FACTOR_ID = 'ibaas.two_factor_id';

    private const AUTH_ENDPOINT_PREFIX = '/v1/auth/';

    public function getToken(Request $request): string
    {
        return (string) $request->session()->get(self::SESSION_TOKEN, '');
    }

    public function getRefreshToken(Request $request): string
    {
        return (string) $request->session()->get(self::SESSION_REFRESH_TOKEN, '');
    }

    public function getTwoFactorId(Request $request): string
    {
        return (string) $request->session()->get(self::SESSION_TWO_FACTOR_ID, '');
    }

    public function shouldAttachToken(Request $request, string $endpoint): bool
    {
        if ($this->getToken($request) === '') {
            return false;
        }

        $isLoginEndpoint = in_array($endpoint, [self::ENDPOINT_LOGIN, self::ENDPOINT_LOGIN_WEBHOOK], true);

        return ! $isLoginEndpoint
            && (! str_starts_with($endpoint, self::AUTH_ENDPOINT_PREFIX) || $endpoint === self::ENDPOINT_LOGOUT);
    }

    public function prefillAuthBody(Request $request, string $endpoint, ?array $body): ?array
    {
        if ($endpoint === self::ENDPOINT_REFRESH && $body === null) {
            $refreshToken = $this->getRefreshToken($request);
            if ($refreshToken !== '') {
                return ['refresh_token' => $refreshToken];
            }
        }

        if ($endpoint === self::ENDPOINT_LOGIN_2FA) {
            $twoFactorId = $this->getTwoFactorId($request);
            $currentId = is_array($body) ? ($body['two_factor_id'] ?? null) : null;
            $hasValidId = is_string($currentId) && trim($currentId) !== '';
            if ($twoFactorId !== '' && ! $hasValidId) {
                $body = is_array($body) ? $body : [];
                $body['two_factor_id'] = $twoFactorId;
            }
        }

        return $body;
    }

    public function syncTokens(Request $request, string $endpoint, mixed $bodyResponse, bool $ok): void
    {
        if ($endpoint === self::ENDPOINT_LOGOUT && $ok) {
            $this->forgetSession($request);

            return;
        }

        if (! is_array($bodyResponse)) {
            return;
        }

        $twoFactorRequired = (bool) data_get($bodyResponse, 'two_factor_required', false);
        $twoFactorId = data_get($bodyResponse, 'two_factor_id');
        if ($twoFactorRequired && is_string($twoFactorId) && $twoFactorId !== '') {
            $request->session()->put(self::SESSION_TWO_FACTOR_ID, $twoFactorId);
        }

        $token = data_get($bodyResponse, 'authorization.token');
        $refreshToken = data_get($bodyResponse, 'authorization.refresh_token');

        if (is_string($token) && $token !== '') {
            $request->session()->put(self::SESSION_TOKEN, $token);
            $request->session()->forget(self::SESSION_TWO_FACTOR_ID);
        }
        if (is_string($refreshToken) && $refreshToken !== '') {
            $request->session()->put(self::SESSION_REFRESH_TOKEN, $refreshToken);
        }
    }

    public function forgetSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_TOKEN,
            self::SESSION_REFRESH_TOKEN,
            self::SESSION_TWO_FACTOR_ID,
        ]);
    }

    /**
     * @return array{has_token: bool, has_refresh_token: bool, has_two_factor_id: bool}
     */
    public function getSessionState(Request $request): array
    {
        return [
            'has_token' => $this->getToken($request) !== '',
            'has_refresh_token' => $this->getRefreshToken($request) !== '',
            'has_two_factor_id' => $this->getTwoFactorId($request) !== '',
        ];
    }
}
