<?php

namespace App\Utils;

use DateTimeImmutable;
use Exception;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;

class GenerateSignedJwt {
    public function call(string $privateKey, string $apiKey, string $endpoint, string $method, mixed $body): string
    {
        $payloadHash = $this->encodePayload($body);

        $claims = $this->buildClaims($apiKey, $endpoint, $method, $payloadHash);

        $publicKey = $this->resolvePublicKey();

        $config = $this->createConfiguration($privateKey, $publicKey);

        $now = new DateTimeImmutable();
        $builder = $config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 minute'));

        foreach ($claims as $claimKey => $claimValue) {
            $builder = $builder->withClaim($claimKey, $claimValue);
        }

        $token = $builder->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    protected function encodePayload(mixed $body): string
    {
        if ($body === null || $body === '') {
            return hash('sha256', '');
        }

        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new Exception('Failed to JSON-encode payload for JWT payload hash.');
        }

        return hash('sha256', $encoded);
    }

    protected function buildClaims(string $apiKey, string $endpoint, string $method, string $payloadHash): array
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d\TH:i:s.u\Z');

        return [
            'timestamp' => $timestamp,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'api_key' => $apiKey,
            'payload_encrypted' => $payloadHash,
        ];
    }

    protected function createConfiguration(string $privateKey, ?string $publicKey = null): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new Sha512(),
            InMemory::plainText($privateKey),
            InMemory::plainText($publicKey ?? '')
        );
    }

    public function resolvePrivateKey(): string
    {
        $raw = getenv('JWT_PRIVATE_KEY');
        if (!empty($raw)) {
            return $raw;
            }

        $path = getenv('JWT_PRIVATE_KEY_PATH');
        if (!empty($path) && file_exists($path)) {
            $contents = file_get_contents($path);
            if ($contents !== false) {
                return $contents;
            }
        }

        throw new Exception('Private key not found. Set JWT_PRIVATE_KEY or JWT_PRIVATE_KEY_PATH in your environment.');
    }

    public function resolvePublicKey(): ?string
    {
        $raw = getenv('JWT_PUBLIC_KEY');
        if (!empty($raw)) {
            return $raw;
        }

        $path = getenv('JWT_PUBLIC_KEY_PATH');
        if (!empty($path) && file_exists($path)) {
            $contents = file_get_contents($path);
            if ($contents !== false) {
                return $contents;
            }
        }

        return null;
    }
}
