 # AAAS Client

 Cliente Laravel para consumir a API do projeto `aaas-client`.

 ## Requisitos

 - PHP 8.2 ou superior  
 - Composer  
 - Node.js (recomendado 20+) e npm  
 - Banco de dados configurado (MySQL/PostgreSQL/SQLite, conforme seu `.env`)

 ## Como começar

 ```bash
 # 1. Clonar o repositório
 git clone https://github.com/Inovanti-Bank/aaas-client.git
 cd aaas-client

 # 2. Instalar tudo e preparar o projeto (.env, key, migrações, build)
 composer setup

 # 3. Subir o servidor backend + frontend (Laravel + Vite)
 composer dev
 ```

 Depois disso, acesse o endereço mostrado no terminal (por padrão `http://127.0.0.1:8000`).

## Configuração da API

- Copie o arquivo `.env.example` para `.env` (se ainda não existir).  
- Ajuste as variáveis necessárias para apontar para a API (URL base, credenciais etc.), de acordo com a documentação da API.
- A documentação completa da API, incluindo como gerar o par de chaves e criar sua **Chave API**, está disponível em:  
  `https://share.apidog.com/116b1949-0d4f-4c99-a001-5516d99f904d/doc-830018`

 ## Testes

 ```bash
 composer test
 ```

