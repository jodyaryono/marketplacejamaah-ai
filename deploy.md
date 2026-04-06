# Deploy Guide — Marketplace Jamaah AI

**VPS:** 103.185.52.146  
**Domain:** marketplacejamaah-ai.jodyaryono.id  
**Path:** /var/www/marketplacejamaah-ai.jodyaryono.id  
**Webhook URL:** `https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter`

---

## SSH Config (~/.ssh/config)

```
Host pasaramal-vps
    HostName 103.185.52.146
    User root
    Port 23232
    IdentityFile ~/.ssh/id_rsa
```

Connect: `ssh pasaramal-vps`

---

## 1. Persiapan VPS (sekali saja)

```bash
# Update sistem
apt update && apt upgrade -y

# Install dependencies
apt install -y nginx postgresql postgresql-contrib \
  php8.2 php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-redis \
  unzip git curl supervisor certbot python3-certbot-nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install Node.js 20 (untuk assets jika diperlukan)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

---

## 2. Setup Database PostgreSQL

```bash
su - postgres
psql

CREATE DATABASE marketplacejamaah;
CREATE USER marketjam WITH ENCRYPTED PASSWORD 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON DATABASE marketplacejamaah TO marketjam;
\q
exit
```

---

## 3. Upload / Clone Project

**Opsi A: Upload via rsync dari lokal (Windows PowerShell):**

```powershell
# Jalankan di PowerShell lokal
rsync -avz --exclude='.git' --exclude='node_modules' --exclude='vendor' `
  -e "ssh -p 23232 -i ~/.ssh/id_rsa" `
  C:/laragon/www/marketplacejamaah-ai/ `
  root@103.185.52.146:/var/www/marketplacejamaah-ai.jodyaryono.id/
```

**Opsi B: Git (jika project sudah di GitHub):**

```bash
cd /var/www
git clone https://github.com/USERNAME/marketplacejamaah-ai.git marketplacejamaah-ai.jodyaryono.id
```

---

## 4. Setup Project di VPS

```bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id

# Install dependencies
composer install --no-dev --optimize-autoloader

# Copy .env
cp .env.example .env
# ATAU upload .env dari lokal:
# scp -P 23232 -i ~/.ssh/id_rsa C:/laragon/www/marketplacejamaah-ai/.env root@103.185.52.146:/var/www/marketplacejamaah-ai.jodyaryono.id/.env

# Edit .env untuk production
nano .env
```

**Isi .env (bagian yang perlu diubah untuk VPS):**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://marketplacejamaah-ai.jodyaryono.id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marketplacejamaah
DB_USERNAME=marketjam
DB_PASSWORD=GANTI_PASSWORD_KUAT

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=marketplace-jamaah
REVERB_APP_KEY=marketplace-key
REVERB_APP_SECRET=marketplace-secret
REVERB_HOST=marketplacejamaah-ai.jodyaryono.id
REVERB_PORT=443
REVERB_SCHEME=https
```

```bash
# Generate app key
php artisan key:generate

# Migrate & seed
php artisan migrate --seed --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data /var/www/marketplacejamaah-ai.jodyaryono.id
chmod -R 755 /var/www/marketplacejamaah-ai.jodyaryono.id
chmod -R 775 /var/www/marketplacejamaah-ai.jodyaryono.id/storage
chmod -R 775 /var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/cache
```

---

## 5. Konfigurasi Nginx

```bash
nano /etc/nginx/sites-available/marketplacejamaah-ai.jodyaryono.id
```

```nginx
server {
    listen 80;
    server_name marketplacejamaah-ai.jodyaryono.id;
    root /var/www/marketplacejamaah-ai.jodyaryono.id/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

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
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Reverb WebSocket
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }
}
```

```bash
# Enable site
ln -s /etc/nginx/sites-available/marketplacejamaah-ai.jodyaryono.id /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

---

## 6. SSL dengan Certbot

```bash
certbot --nginx -d marketplacejamaah-ai.jodyaryono.id
# Ikuti instruksi, pilih redirect HTTP → HTTPS
```

---

## 7. Supervisor — Queue Worker & Reverb

```bash
nano /etc/supervisor/conf.d/marketplacejamaah.conf
```

```ini
[program:marketplacejamaah-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/marketplacejamaah-ai.jodyaryono.id/artisan queue:work --queue=agents --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/marketplacejamaah-ai.jodyaryono.id/storage/logs/queue.log

[program:marketplacejamaah-reverb]
process_name=%(program_name)s
command=php /var/www/marketplacejamaah-ai.jodyaryono.id/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/marketplacejamaah-ai.jodyaryono.id/storage/logs/reverb.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start all
supervisorctl status
```

---

## 8. DNS — Arahkan Domain

Di panel DNS domain `jodyaryono.id`, tambah record:

```
Type: A
Name: marketplacejamaah-ai
Value: 103.185.52.146
TTL: 3600
```

---

## 9. Register Webhook di WhaCentre

1. Login ke [app.whacenter.com](https://app.whacenter.com)
2. Buka device `59087f966f4f8cc3385569ef6481cc29`
3. Setting → Webhook URL:
    ```
    https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter
    ```
4. Save

---

## 10. Verifikasi Deployment

```bash
# Cek status semua service
systemctl status nginx php8.2-fpm postgresql
supervisorctl status

# Cek log
tail -f /var/www/marketplacejamaah-ai.jodyaryono.id/storage/logs/laravel.log
tail -f /var/www/marketplacejamaah-ai.jodyaryono.id/storage/logs/queue.log

# Test webhook endpoint
curl -X POST https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

---

## Update Deployment (setelah push perubahan)

```bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
supervisorctl restart all
```

---

## Login Dashboard

- URL: `https://marketplacejamaah-ai.jodyaryono.id`
- Admin: `admin@marketplacejamaah.id` / _(lihat .env atau password manager)_
- Operator: `operator@marketplacejamaah.id` / _(lihat .env atau password manager)_
