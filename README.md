## AaaS Client

### 1. Instalação

```bash
composer install
composer run dev
```

Se ainda não tiver o `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

### 2. Variáveis de ambiente obrigatórias (`.env`)

```dotenv
JWT_PRIVATE_KEY_PATH=/caminho/completo/para/storage/keys/jwtECDSASHA512.key
JWT_PUBLIC_KEY_PATH=/caminho/completo/para/storage/keys/jwtECDSASHA512.key.pub
BASE_URL=https://seu-ambiente-ibaas
API_KEY=sua_api_key
```

### 3. Onde devem ficar as chaves

Coloque os arquivos de chave no diretório:

- `storage/keys/jwtECDSASHA512.key` (privada)
- `storage/keys/jwtECDSASHA512.key.pub` (pública)

Se a pasta não existir:

```bash
mkdir -p storage/keys
```
