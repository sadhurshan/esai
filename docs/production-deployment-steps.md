# Production Deployment Steps (Lightsail LAMP + AI Microservice)

This document covers the base deployment runbook for a fresh Lightsail LAMP instance and the additional setup required to run the AI microservice on the same Lightsail instance using a Python venv.

## Base Runbook (Lightsail LAMP + PHP 8.3.23 + domain + Supervisor)

### 1) Connect to the instance

```bash
ssh ubuntu@YOUR_PUBLIC_IP
```

### 2) Update OS packages

```bash
sudo apt update
sudo apt -y upgrade
```

### 3) Install required packages

```bash
sudo apt -y install git unzip curl supervisor redis-server
```

### 4) Install Composer (if missing)

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 5) Install Node.js LTS

```bash
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt -y install nodejs
node -v
npm -v
```

### 6) Clone the repository

```bash
cd /var/www
sudo git clone YOUR_GIT_URL elements-supply-ai
sudo chown -R $USER:$USER /var/www/elements-supply-ai
```

### 7) Configure environment

```bash
cd /var/www/elements-supply-ai
cp .env.example .env
```

Update `.env` with:

- `APP_ENV=production`
- `APP_URL=https://app.elementssupplyai.com`
- Database credentials
- Redis config
- S3 config (if required)

### 8) Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 9) Generate app key

```bash
php artisan key:generate
```

### 10) Build frontend assets

```bash
npm install
npm run build
```

### 11) Run migrations

```bash
php artisan migrate --force
```

### 12) Storage link

```bash
php artisan storage:link
```

### 13) Cache optimization

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 14) Apache vhost for app.elementssupplyai.com

```bash
sudo a2enmod rewrite
sudo tee /etc/apache2/sites-available/app.elementssupplyai.com.conf > /dev/null <<'EOF'
<VirtualHost *:80>
	ServerName app.elementssupplyai.com

	DocumentRoot /var/www/elements-supply-ai/public

	<Directory /var/www/elements-supply-ai/public>
		AllowOverride All
		Require all granted
	</Directory>

	ErrorLog ${APACHE_LOG_DIR}/elements-error.log
	CustomLog ${APACHE_LOG_DIR}/elements-access.log combined
</VirtualHost>
EOF
```

Enable the site and reload Apache:

```bash
sudo a2ensite app.elementssupplyai.com.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### 15) SSL with Let’s Encrypt

```bash
sudo apt -y install certbot python3-certbot-apache
sudo certbot --apache -d app.elementssupplyai.com
```

### 16) Supervisor for queues

Queue names used by jobs in this codebase include:

- `default`
- `notifications`
- `webhooks`
- `exports`
- `downloads`
- `maintenance`
- `ai-indexing`

If you want a single worker to handle all of them, pass a comma-separated list via `--queue=...`.

Create a Supervisor program file:

```bash
sudo tee /etc/supervisor/conf.d/laravel-queue.conf > /dev/null <<'EOF'
[program:laravel-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/elements-supply-ai/artisan queue:work redis --queue=default,notifications,webhooks,exports,downloads,maintenance,ai-indexing --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/elements-supply-ai/storage/logs/queue.log
EOF
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue:*
sudo supervisorctl status
```

### 17) Scheduler (cron)

```bash
sudo crontab -e
```

Add:

```bash
* * * * * php /var/www/elements-supply-ai/artisan schedule:run >> /dev/null 2>&1
```

### 18) Permissions

```bash
sudo chown -R www-data:www-data /var/www/elements-supply-ai/storage /var/www/elements-supply-ai/bootstrap/cache
```

## Prerequisites

- Laravel app deployed at `/var/www/elements-supply-ai`
- `redis-server` installed and running
- Supervisor installed
- Python 3.11+ installed

## 1) Create a Python venv for the AI microservice

```bash
cd /var/www/elements-supply-ai/ai_microservice
python3 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
```

## 2) Configure the AI microservice environment

Create a `.env` or export environment variables depending on how the microservice loads config.

At minimum, ensure these are set for the service process:

- `AI_SERVICE_HOST=127.0.0.1`
- `AI_SERVICE_PORT=8000`
- `OPENAI_API_KEY=...` (or your provider key)
- `EMBEDDING_PROVIDER=...`
- `VECTOR_STORE=...`

If the microservice reads config from a file, place it in `ai_microservice/config/` as required.

## 3) Configure Laravel to reach the AI microservice

In the Laravel `.env`:

```
AI_SERVICE_URL=http://127.0.0.1:8000
```

Then refresh caches:

```bash
cd /var/www/elements-supply-ai
php artisan config:cache
```

## 4) Run the AI microservice with Supervisor

Create a Supervisor program file:

```bash
sudo tee /etc/supervisor/conf.d/ai-microservice.conf > /dev/null <<'EOF'
[program:ai-microservice]
command=/var/www/elements-supply-ai/ai_microservice/.venv/bin/python /var/www/elements-supply-ai/ai_microservice/app.py
directory=/var/www/elements-supply-ai/ai_microservice
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/elements-supply-ai/ai_microservice/logs/ai-service.log
environment=AI_SERVICE_HOST="127.0.0.1",AI_SERVICE_PORT="8000"
EOF
```

Ensure the log directory exists:

```bash
sudo mkdir -p /var/www/elements-supply-ai/ai_microservice/logs
sudo chown -R www-data:www-data /var/www/elements-supply-ai/ai_microservice/logs
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ai-microservice
sudo supervisorctl status
```

## 5) Firewall and network

Keep the AI service bound to `127.0.0.1` so it is not exposed publicly. No inbound firewall rule is required.

## 6) Health check

From the instance:

```bash
curl -s http://127.0.0.1:8000/health | cat
```

If there is no `/health` endpoint, use the actual status route defined in the service.

## 7) Verify from Laravel

Trigger an AI feature in the UI and verify logs:

- Laravel logs: `/var/www/elements-supply-ai/storage/logs/laravel.log`
- AI logs: `/var/www/elements-supply-ai/ai_microservice/logs/ai-service.log`

## Notes

- If you use a different provider (Azure, Anthropic, etc.), set the provider-specific env variables required by the microservice.
- If you change the AI service port, update both Supervisor and `AI_SERVICE_URL`.
