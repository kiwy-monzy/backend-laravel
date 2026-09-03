# VPS Deployment Guide

## Option 1: Docker Deployment (Recommended)

### 1. Upload files to VPS
```bash
scp -r ./backend-laravel user@your-vps:/var/www/fge-backend
scp docker-compose.yml user@your-vps:/var/www/fge-backend/
```

### 2. SSH into VPS and configure environment
```bash
ssh user@your-vps
cd /var/www/fge-backend

# Copy and edit environment file
cp backend-laravel/.env.production backend-laravel/.env
nano backend-laravel/.env
# Update: APP_URL, DB_PASSWORD, JWT_SECRET
```

### 3. Generate secrets
```bash
cd backend-laravel
php artisan key:generate --force
php artisan jwt:secret --force
```

### 4. Start with Docker
```bash
docker compose up -d --build
```

### 5. Run migrations
```bash
docker compose exec app php artisan migrate --force
```

---

## Option 2: Traditional Server Setup

### Server Requirements
- Ubuntu 22.04 LTS
- Nginx
- PHP 8.3+ (with: pdo_sqlite, mbstring, xml, curl, zip)
- PostgreSQL 14+ or MySQL 8+
- Redis
- Composer 2

### Step 1: Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP and extensions
sudo apt install -y php8.3 php8.3-fpm php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-sqlite3 php8.3-redis

# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Install Redis
sudo apt install -y redis-server

# Install Nginx
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 2: Configure Database
```bash
sudo -u postgres psql

CREATE DATABASE fge_db;
CREATE USER fge_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE fge_db TO fge_user;
\q
```

### Step 3: Configure Nginx
```bash
sudo nano /etc/nginx/sites-available/fge-backend
```
Paste this configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/fge-backend/backend-laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/fge-backend /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### Step 4: Upload & Configure Application
```bash
# Upload files
scp -r ./backend-laravel user@your-vps:/var/www/fge-backend

# Install dependencies
cd /var/www/fge-backend/backend-laravel
composer install --optimize-autoloader --no-dev

# Configure environment
cp .env.production .env
nano .env  # Edit all settings

# Generate keys
php artisan key:generate --force
php artisan jwt:secret --force

# Set permissions
sudo chown -R www-data:www-data /var/www/fge-backend/backend-laravel
chmod -R 775 /var/www/fge-backend/backend-laravel/storage
chmod -R 775 /var/www/fge-backend/backend-laravel/bootstrap/cache
```

### Step 5: Run Migrations & Cache
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 6: Setup Queue Worker (Supervisor)
```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/fge-worker.conf
```
```
[program:fge-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/fge-backend/backend-laravel/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
user=www-data
```
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start fge-worker:*
```

### Step 7: SSL (Let's Encrypt)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## Essential Commands

```bash
# View logs
tail -f storage/logs/laravel.log

# Queue worker restart
sudo supervisorctl restart fge-worker:*

# Clear all caches
php artisan optimize:clear

# Update application
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
sudo supervisorctl restart fge-worker:*
```
