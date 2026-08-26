<?php

namespace Tests\Unit;

use App\Utils\GenerateSignedJwt;
use Tests\Support\GeneratesEcdsaKeys;
use Tests\TestCase;

class GenerateSignedJwtTest extends TestCase
{
    use GeneratesEcdsaKeys;

    public function test_it_generates_a_token_with_the_expected_claims(): void
    {
        $keys = $this->generateEcdsaKeyPair();
        $body = ['amount' => 100, 'description' => 'Pagamento de teste'];

        $token = (new GenerateSignedJwt)->call($keys['private'], 'my-api-key', '/v1/aaas/account', 'post', $body);

        $claims = $this->decodeJwtClaims($token);
        $expectedHash = hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->assertSame('POST', $claims['method']);
        $this->assertSame('/v1/aaas/account', $claims['endpoint']);
        $this->assertSame('my-api-key', $claims['api_key']);
        $this->assertSame($expectedHash, $claims['payload_encrypted']);
        $this->assertArrayHasKey('timestamp', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function test_it_hashes_an_empty_body_as_empty_string(): void
    {
        $keys = $this->generateEcdsaKeyPair();

        $token = (new GenerateSignedJwt)->call($keys['private'], 'my-api-key', '/v1/aaas/account', 'GET', null);

        $claims = $this->decodeJwtClaims($token);
        $this->assertSame(hash('sha256', ''), $claims['payload_encrypted']);
    }

    public function test_it_resolves_the_public_key_from_config(): void
    {
        $keys = $this->generateEcdsaKeyPair();
        config(['services.aaas.jwt.public_key' => $keys['public']]);

        $this->assertSame($keys['public'], (new GenerateSignedJwt)->resolvePublicKey());
    }

    public function test_public_key_is_optional(): void
    {
        config([
            'services.aaas.jwt.public_key' => null,
            'services.aaas.jwt.public_key_path' => null,
        ]);

        $this->assertNull((new GenerateSignedJwt)->resolvePublicKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtClaims(string $token): array
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT deve ter header.payload.signature');

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $this->assertIsString($payload);

        return json_decode($payload, true);
    }
}
