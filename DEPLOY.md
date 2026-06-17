# Deploy - AWS EC2 (Ubuntu 22.04/24.04)

## 1. Dependências (PHP 8.2, Nginx, Node, Composer)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl zip unzip git nginx supervisor

# PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-{fpm,cli,common,mysql,zip,gd,mbstring,curl,xml,bcmath,sqlite3}

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20+
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2. Setup do Projeto

```bash
cd /var/www/
sudo git clone https://github.com/Inovanti-Bank/aaas-client.git
sudo chown -R $USER:$USER /var/www/aaas-client
cd aaas-client

# Dependências
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Permissões
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 3. Variáveis de Ambiente (.env)

Copie o `.env.example` para `.env` e configure o banco de dados. Insira o conteúdo completo das chaves JWT (com quebra de linha) envolvido em aspas duplas:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com.br

JWT_PRIVATE_KEY="-----BEGIN EC PRIVATE KEY-----
MIIB...
-----END EC PRIVATE KEY-----"

JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
MIGb...
-----END PUBLIC KEY-----"

BASE_URL=https://inovanti.ibaas-stg.inovanti.tec.br
API_KEY_IAAAS=sua_api_key_iaaas
```

Finalize o setup do Laravel:

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 4. Nginx

Criar configuração `/etc/nginx/sites-available/aaas-client`:

```nginx
server {
    listen 80;
    server_name seu-dominio.com.br;
    root /var/www/aaas-client/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Ativar site e reiniciar serviço:

```bash
sudo ln -s /etc/nginx/sites-available/aaas-client /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 5. Supervisor (Workers) (OPCIONAL)

Criar configuração `/etc/supervisor/conf.d/aaas-client-worker.conf`:

```ini
[program:aaas-client-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/aaas-client/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/aaas-client/storage/logs/worker.log
```

Atualizar o Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start aaas-client-worker:*
```
