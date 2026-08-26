<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\GeneratesEcdsaKeys;
use Tests\TestCase;

class ConsoleSendTest extends TestCase
{
    use GeneratesEcdsaKeys;

    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKeyPem = $this->generateEcdsaKeyPair()['private'];

        config([
            'services.aaas.iaaas.base_url' => 'https://iaaas.test',
            'services.aaas.ibaas.base_url' => 'https://ibaas.test',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function iaaasCredentialsSession(): array
    {
        return [
            'iaaas_api_key' => 'test-api-key',
            'iaaas_private_key' => $this->privateKeyPem,
        ];
    }

    public function test_console_page_renders(): void
    {
        $this->withoutVite()
            ->get('/')
            ->assertOk()
            ->assertViewIs('console.index')
            ->assertSee('Humu Service');
    }

    public function test_endpoint_is_required(): void
    {
        $this->postJson(route('console.send'), ['method' => 'GET'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint']);
    }

    public function test_invalid_json_body_returns_422(): void
    {
        $response = $this->withSession($this->iaaasCredentialsSession())
            ->postJson(route('console.send'), [
                'service' => 'iaaas',
                'endpoint' => '/v1/aaas/account',
                'method' => 'POST',
                'body' => '{invalid-json',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid JSON body', $response->json('error'));
    }

    public function test_iaaas_request_sends_signed_jwt_headers(): void
    {
        Http::fake(['https://iaaas.test/*' => Http::response(['data' => 'ok'])]);

        $response = $this->withSession($this->iaaasCredentialsSession())
            ->postJson(route('console.send'), [
                'service' => 'iaaas',
                'endpoint' => '/v1/aaas/account/list',
                'method' => 'GET',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.url', 'https://iaaas.test/v1/aaas/account/list');
        $this->assertNotEmpty($response->json('token'));

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://iaaas.test/v1/aaas/account/list'
                && str_starts_with($request->header('X-auth-token')[0] ?? '', 'Bearer ')
                && ($request->header('X-api-key')[0] ?? null) === 'test-api-key';
        });
    }

    public function test_iaaas_without_credentials_returns_422(): void
    {
        Http::fake();

        $response = $this->postJson(route('console.send'), [
            'service' => 'iaaas',
            'endpoint' => '/v1/aaas/account',
            'method' => 'GET',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Configurar chaves', $response->json('error'));
        Http::assertNothingSent();
    }

    public function test_save_iaaas_credentials_stores_session_and_cookies(): void
    {
        $response = $this->postJson(route('console.iaaas-keys'), [
            'api_key' => 'my-api-key',
            'private_key' => $this->privateKeyPem,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertSessionHas('iaaas_api_key', 'my-api-key')
            // O middleware TrimStrings apara o \n final do PEM enviado no request
            ->assertSessionHas('iaaas_private_key', trim($this->privateKeyPem))
            ->assertCookie('iaaas_api_key')
            ->assertCookie('iaaas_private_key');
    }

    public function test_save_iaaas_credentials_rejects_invalid_private_key(): void
    {
        $response = $this->postJson(route('console.iaaas-keys'), [
            'api_key' => 'my-api-key',
            'private_key' => 'not-a-pem-key',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Chave privada inválida', $response->json('error'));
    }

    public function test_save_iaaas_credentials_requires_both_fields(): void
    {
        $this->postJson(route('console.iaaas-keys'), ['api_key' => 'only-key'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['private_key']);
    }

    public function test_ibaas_login_stores_session_tokens(): void
    {
        Http::fake([
            'https://ibaas.test/*' => Http::response([
                'authorization' => ['token' => 'session-token', 'refresh_token' => 'refresh-token'],
            ]),
        ]);

        $response = $this->postJson(route('console.send'), [
            'service' => 'ibaas',
            'endpoint' => '/v1/auth/login',
            'method' => 'POST',
            'body' => '{"username":"user","password":"secret"}',
        ]);

        $response->assertOk()
            ->assertJsonPath('ibaas_session.has_token', true)
            ->assertJsonPath('ibaas_session.has_refresh_token', true)
            ->assertSessionHas('ibaas.token', 'session-token')
            ->assertSessionHas('ibaas.refresh_token', 'refresh-token');
    }

    public function test_ibaas_request_attaches_session_token(): void
    {
        Http::fake(['https://ibaas.test/*' => Http::response(['data' => 'ok'])]);

        $this->withSession(['ibaas.token' => 'stored-token'])
            ->postJson(route('console.send'), [
                'service' => 'ibaas',
                'endpoint' => '/v1/baas/account',
                'method' => 'GET',
            ])
            ->assertOk();

        Http::assertSent(function (ClientRequest $request): bool {
            return ($request->header('Authorization')[0] ?? null) === 'Bearer stored-token';
        });
    }

    public function test_ibaas_401_triggers_refresh_and_retry(): void
    {
        $endpointCalls = 0;
        Http::fake(function (ClientRequest $request) use (&$endpointCalls) {
            if (str_contains($request->url(), '/v1/auth/refresh')) {
                return Http::response([
                    'authorization' => ['token' => 'renewed-token', 'refresh_token' => 'renewed-refresh'],
                ]);
            }

            $endpointCalls++;

            return $endpointCalls === 1
                ? Http::response(['message' => 'unauthorized'], 401)
                : Http::response(['data' => 'ok']);
        });

        $response = $this->withSession(['ibaas.token' => 'expired-token', 'ibaas.refresh_token' => 'refresh-1'])
            ->postJson(route('console.send'), [
                'service' => 'ibaas',
                'endpoint' => '/v1/baas/account',
                'method' => 'GET',
            ]);

        $response->assertOk()
            ->assertJsonPath('body.data', 'ok')
            ->assertSessionHas('ibaas.token', 'renewed-token');
        $this->assertSame(2, $endpointCalls, 'A requisição original deve ser reenviada após o refresh.');
    }

    public function test_ibaas_logout_clears_session(): void
    {
        Http::fake(['https://ibaas.test/*' => Http::response(['message' => 'bye'])]);

        $this->withSession(['ibaas.token' => 'stored-token', 'ibaas.refresh_token' => 'refresh-1'])
            ->postJson(route('console.send'), [
                'service' => 'ibaas',
                'endpoint' => '/v1/auth/logout',
                'method' => 'POST',
            ])
            ->assertOk()
            ->assertJsonPath('ibaas_session.has_token', false)
            ->assertSessionMissing('ibaas.token')
            ->assertSessionMissing('ibaas.refresh_token');
    }

    public function test_unexpected_errors_do_not_leak_details(): void
    {
        Http::fake();

        $response = $this->withSession([
            'iaaas_api_key' => 'test-api-key',
            'iaaas_private_key' => 'chave-corrompida-que-nao-e-pem',
        ])->postJson(route('console.send'), [
            'service' => 'iaaas',
            'endpoint' => '/v1/aaas/account',
            'method' => 'GET',
        ]);

        $response->assertStatus(500);
        $this->assertStringNotContainsString('openssl', strtolower((string) $response->json('error')));
        $this->assertStringContainsString('Falha ao processar', $response->json('error'));
    }
}
