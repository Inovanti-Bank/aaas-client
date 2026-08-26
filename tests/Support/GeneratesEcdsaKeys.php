<?php

namespace Tests\Support;

trait GeneratesEcdsaKeys
{
    protected function generateEcdsaKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp521r1',
        ]);
        $this->assertNotFalse($resource, 'Falha ao gerar chave ECDSA para o teste.');

        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);

        return [
            'private' => $privateKeyPem,
            'public' => $details['key'],
        ];
    }
}
