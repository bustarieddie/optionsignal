# Production Deployment — OptionSignal Pro

A practical, copy-pasteable guide to deploying **OptionSignal Pro** (Laravel 12 + Sneat
Blade + Fortify + Sanctum + spatie/laravel-permission + Laravel Reverb + league/csv) to a
production Linux server.

This app is **not** a static site. In addition to PHP-FPM behind nginx, it needs:

- a **queue worker** — the TradingView webhook hands signals to a queued job
  (`ProcessTradingViewSignal`). **Without a running worker, webhooks are accepted but never
  processed** (no graded signals, no notifications, no live push).
- a **Reverb WebSocket server** — powers the live-updating dashboard. It degrades gracefully
  if absent (signals still persist), but you want it running.
- a **cron entry** for the Laravel scheduler.

Dev defaults to SQLite + the `database` queue/cache drivers. **Production should use
MySQL/PostgreSQL + Redis.**

---

## 1. Server requirements

| Component | Version / notes |
|-----------|-----------------|
| PHP       | **8.3+** (`composer.json` requires `^8.2`; use 8.3 in prod) |
| Composer  | 2.x |
| Node      | **20+** / npm (build-time only) |
| Database  | **MySQL 8** or **PostgreSQL 14+** |
| Redis     | 6+ (queue, cache, sessions, optional Reverb scaling) |
| Web server| nginx + PHP-FPM |
| TLS       | Let's Encrypt / Certbot |

### Required PHP extensions

```bash
# Debian/Ubuntu example
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-cli \
  php8.3-mysql \
  php8.3-mbstring php8.3-bcmath php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-intl php8.3-redis
# Core: openssl, pdo, ctype, json, tokenizer, fileinfo are built into the php8.3 base.
```

`intervention/image` (screenshot uploads in the trade journal) needs **gd** or **imagick**.
For PostgreSQL swap `php8.3-mysql` for `php8.3-pgsql`.

Install the rest:

```bash
# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20 (NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL + Redis
sudo apt install -y mysql-server redis-server
sudo systemctl enable --now redis-server
```

Create the database and a dedicated user:

```sql
CREATE DATABASE optionsignal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'optionsignal'@'127.0.0.1' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON optionsignal.* TO 'optionsignal'@'127.0.0.1';
FLUSH PRIVILEGES;
```

---

## 2. Deploy steps

Deploy as a non-root user (e.g. `deploy`); nginx/PHP-FPM run as `www-data`.

```bash
# 1. Clone
cd /var/www
git clone <your-repo-url> optionsignal-pro
cd optionsignal-pro

# 2. PHP dependencies (production: no dev packages, optimized autoloader)
composer install --no-dev --optimize-autoloader

# 3. Front-end dependencies + build
npm ci --legacy-peer-deps   # template devDeps need legacy peer resolution
npm run build
npm run build               # RUN TWICE on first deploy — see note below

# 4. Environment
cp .env.example .env
# ...edit .env per section 3 below...
php artisan key:generate

# 5. Database schema
php artisan migrate --force

# 6. Seed ONLY production data (roles/permissions + the system default strategy)
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=DefaultStrategySeeder --force

# 7. Storage symlink (public/storage -> storage/app/public, for screenshot uploads)
php artisan storage:link

# 8. Cache config, routes, views, events
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> **Run `npm run build` TWICE on a clean checkout.** This is a documented Sneat-template
> quirk: the icon plugin generates `iconify.css` during the *first* build, and the *second*
> build picks it up into the Vite manifest. Skipping the second pass yields missing icons.

> **Do NOT run the full `DatabaseSeeder` in production.** It seeds **demo users**
> (`admin@optionsignal.local`, etc. with password `password`) and demo watchlists. Run only
> `RolesAndPermissionsSeeder` and `DefaultStrategySeeder` as shown above. Create your real
> admin user manually (e.g. via `php artisan tinker`) and assign the `Admin` role.

> **`VITE_REVERB_*` must be set in `.env` BEFORE `npm run build`.** Those values are compiled
> into the JS bundle at build time (see `resources/assets/js/osp-echo.js`). If you change them
> later you must rebuild.

File permissions (writable dirs owned/writable by the web user):

```bash
sudo chown -R deploy:www-data /var/www/optionsignal-pro
sudo find /var/www/optionsignal-pro -type d -exec chmod 755 {} \;
sudo find /var/www/optionsignal-pro -type f -exec chmod 644 {} \;
sudo chmod -R ug+rwX /var/www/optionsignal-pro/storage \
                     /var/www/optionsignal-pro/bootstrap/cache
```

---

## 3. Production `.env`

Start from `.env.example` and set the key block below. Generate strong random values for
`APP_KEY` (via `key:generate`), `TRADINGVIEW_WEBHOOK_SECRET`, and the `REVERB_APP_*` secrets.

```dotenv
APP_NAME="OptionSignal Pro"
APP_ENV=production
APP_KEY=                      # set by `php artisan key:generate`
APP_DEBUG=false
APP_URL=https://optionsignal.example.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

# --- Database (MySQL) ---
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=optionsignal
DB_USERNAME=optionsignal
DB_PASSWORD=a-strong-password
# PostgreSQL instead: DB_CONNECTION=pgsql, DB_PORT=5432

# --- Queue / Cache / Sessions -> Redis ---
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis          # or `database` if you prefer DB-backed sessions
SESSION_LIFETIME=120

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null           # set a password in production Redis if exposed
REDIS_PORT=6379

# --- Broadcasting / Reverb (WebSocket) ---
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=optionsignal
REVERB_APP_KEY=generate-a-random-key
REVERB_APP_SECRET=generate-a-random-secret
REVERB_HOST=optionsignal.example.com   # PUBLIC host the browser connects to
REVERB_PORT=443                         # public TLS port (nginx terminates, see section 5)
REVERB_SCHEME=https
# Reverb process binds locally on 0.0.0.0:8080 (see config/reverb.php REVERB_SERVER_*).
# REVERB_HOST/PORT/SCHEME above describe the PUBLIC endpoint the client reaches via nginx.

# These are compiled into the JS bundle at build time — set BEFORE `npm run build`.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# --- Mail (real SMTP in production) ---
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-pass
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="alerts@optionsignal.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# --- App-specific ---
# Shared secret TradingView sends INSIDE the webhook JSON body. Make it long + random.
TRADINGVIEW_WEBHOOK_SECRET=use-a-64-char-random-string

# Optional Telegram alerts (leave blank to disable)
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

Generate strong secrets:

```bash
php artisan key:generate                 # APP_KEY
openssl rand -base64 48                   # TRADINGVIEW_WEBHOOK_SECRET / REVERB secrets
```

> The public `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` (used by `config/broadcasting.php`
> and the JS client) describe where the **browser** connects — your domain on `443` over
> `https`/`wss`. The Reverb **process** itself listens on `0.0.0.0:8080` locally
> (`REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` in `config/reverb.php`); nginx reverse-proxies
> `wss://your-domain/app/...` to that local port (section 5).

---

## 4. Queue worker (REQUIRED)

The webhook enqueues `ProcessTradingViewSignal`. **No worker = no processed signals.** Run it
under systemd (or Supervisor) so it restarts on crash and on boot.

### systemd unit

`/etc/systemd/system/optionsignal-queue.service`:

```ini
[Unit]
Description=OptionSignal Pro queue worker
After=network.target redis-server.service mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/optionsignal-pro
ExecStart=/usr/bin/php /var/www/optionsignal-pro/artisan queue:work \
  --tries=3 --max-time=3600 --sleep=3
# Stop gracefully on deploy/restart
ExecStop=/usr/bin/php /var/www/optionsignal-pro/artisan queue:restart

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now optionsignal-queue
sudo systemctl status optionsignal-queue
```

### Supervisor alternative

`/etc/supervisor/conf.d/optionsignal-queue.conf`:

```ini
[program:optionsignal-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/optionsignal-pro/artisan queue:work --tries=3 --max-time=3600 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/optionsignal-pro/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start optionsignal-queue:*
```

> **After every deploy, run `php artisan queue:restart`.** Long-running workers hold the old
> code in memory; `queue:restart` signals them to finish the current job and exit, so systemd/
> Supervisor relaunches them with the new code. `--max-time=3600` also recycles each worker
> hourly as a safety net.

---

## 5. Reverb (WebSocket server)

Run the Reverb process under systemd. It binds locally on `0.0.0.0:8080`; nginx proxies the
public `wss://your-domain/app/...` to it (TLS terminated by nginx).

`/etc/systemd/system/optionsignal-reverb.service`:

```ini
[Unit]
Description=OptionSignal Pro Reverb WebSocket server
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/optionsignal-pro
ExecStart=/usr/bin/php /var/www/optionsignal-pro/artisan reverb:start \
  --host=0.0.0.0 --port=8080

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now optionsignal-reverb
```

The browser client (`resources/assets/js/osp-echo.js`) connects to `wsHost = VITE_REVERB_HOST`
on `VITE_REVERB_PORT` (default 8080) using the Pusher protocol over the standard Reverb `/app`
path, with `forceTLS` on when `VITE_REVERB_SCHEME=https`. In production you point those at your
public domain/`443` and let nginx proxy `/app` down to `127.0.0.1:8080` (see the nginx block in
section 6).

> **Single-node Reverb needs no Redis.** Only enable Reverb's Redis-backed scaling if you run
> **multiple** Reverb nodes behind a load balancer. To do so set
> `REVERB_SCALING_ENABLED=true` and the `REDIS_*` connection (already configured for queue/
> cache) — see `config/reverb.php` → `servers.reverb.scaling`.

---

## 6. nginx server block

Full HTTPS server block: PHP-FPM for the app, plus a `/app` location reverse-proxying the
Reverb WebSocket. Obtain the certificate first with Certbot.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name optionsignal.example.com;
    # Force HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name optionsignal.example.com;

    root /var/www/optionsignal-pro/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/optionsignal.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/optionsignal.example.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    client_max_body_size 12M;   # allows trade-journal screenshot uploads

    charset utf-8;

    # ----- Reverb WebSocket proxy (wss://host/app/...) -----
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 600s;
    }

    # ----- Laravel front controller -----
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    error_log  /var/log/nginx/optionsignal.error.log;
    access_log /var/log/nginx/optionsignal.access.log;
}
```

TLS via Certbot:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d optionsignal.example.com
sudo nginx -t && sudo systemctl reload nginx
```

> Reverb's WebSocket lives on the standard `/app` path (Pusher protocol). The `Upgrade` /
> `Connection "Upgrade"` headers are what turn the proxied request into a WebSocket; without
> them the live dashboard silently fails to connect.

---

## 7. Scheduler (cron)

Add the Laravel scheduler so any scheduled tasks fire. Run as the web user:

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/optionsignal-pro && php artisan schedule:run >> /dev/null 2>&1
```

> The app does not register scheduled tasks today; this entry costs nothing and means future
> scheduled jobs work without touching the server again.

---

## 8. TradingView webhook

- **URL:** `https://optionsignal.example.com/api/webhooks/tradingview`
- It **must be public and HTTPS** (TradingView only posts to reachable HTTPS endpoints).
- The shared secret travels **inside the JSON body** (`"secret": "..."`), not in a header —
  TradingView cannot send custom headers. It is validated with a timing-safe compare and never
  persisted. Set the matching value in `TRADINGVIEW_WEBHOOK_SECRET` and in the Pine script's
  `secret` input.
- The endpoint is rate-limited and de-duplicates repeat signals.

In TradingView: create an alert with condition **"Any alert() function call"**, set the
webhook URL above, and ensure the Pine script's `secret` input equals
`TRADINGVIEW_WEBHOOK_SECRET`. (Pine template: `resources/pine/optionsignal-pro.pine`.)

> Confirm the **queue worker is running** (section 4) — the webhook only *lands* the raw
> payload; the queued job does the scoring, grading and notifications.

---

## 9. Security checklist

- [ ] `APP_ENV=production` and **`APP_DEBUG=false`** (never expose stack traces / config).
- [ ] Strong, unique `APP_KEY` (`php artisan key:generate`).
- [ ] Long random `TRADINGVIEW_WEBHOOK_SECRET` and `REVERB_APP_SECRET` (e.g. `openssl rand`).
- [ ] **HTTPS only** — HTTP 301-redirects to HTTPS; Reverb over `wss`.
- [ ] Webhook rate limiting is already enabled in-app; keep it on. Optionally add an nginx
      `limit_req` zone for `/api/webhooks/tradingview` as defense in depth.
- [ ] **Sanctum tokens are scoped** — new tokens default to `mcp:read`; grant `mcp:write` or
      `trades:write` only deliberately. MCP calls are logged to `mcp_audit_logs`.
- [ ] **Do NOT seed demo users** — run only `RolesAndPermissionsSeeder` +
      `DefaultStrategySeeder`, not the full `DatabaseSeeder`.
- [ ] File permissions: `storage/` and `bootstrap/cache/` writable by the web user; app code
      not world-writable.
- [ ] Keep **`.env` out of git** (it already is via `.gitignore`); never commit tokens/secrets.
- [ ] The MCP endpoint (`/mcp/optionsignal`) returns 401 without a valid verified token —
      never commit a token into `claude_desktop_config.json` in a repo.
- [ ] Set a Redis password if Redis is reachable beyond localhost.

---

## 10. Laravel Forge (fast path)

Forge provisions nginx, PHP-FPM, MySQL/Postgres and Redis for you, and manages the queue
worker, scheduler and TLS. To deploy this app on Forge:

1. **Create the site** (PHP 8.3), point it at your repo, enable the **Let's Encrypt**
   certificate. Forge writes the nginx + PHP-FPM config and the scheduler cron automatically.
2. **Environment:** paste the section-3 `.env` block into the site's Environment editor
   (MySQL creds, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `BROADCAST_CONNECTION=reverb`,
   all `REVERB_*` + `VITE_REVERB_*`, mail, `TRADINGVIEW_WEBHOOK_SECRET`).
3. **Deploy script** (Site → Deploy Script):

   ```bash
   cd /home/forge/optionsignal.example.com
   git pull origin main
   composer install --no-dev --optimize-autoloader
   npm ci --legacy-peer-deps
   npm run build && npm run build      # twice — iconify.css quirk
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   php artisan queue:restart
   ```

   On the **first** deploy also run, once, from the Forge command runner:
   `php artisan db:seed --class=RolesAndPermissionsSeeder --force` and
   `php artisan db:seed --class=DefaultStrategySeeder --force`, plus `php artisan storage:link`.
4. **Queue worker:** add a Forge **Queue Worker** (connection `redis`, `--tries=3`,
   `--max-time=3600`). Forge keeps it alive and restarts it on each deploy.
5. **Reverb daemon:** add a Forge **Daemon** running
   `php artisan reverb:start --host=0.0.0.0 --port=8080`, then add the `/app` WebSocket
   proxy block (section 6) to the site's nginx config via Forge's nginx editor.

---

## 11. Updates / near-zero-downtime redeploys

```bash
cd /var/www/optionsignal-pro

php artisan down                 # optional maintenance window

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci --legacy-peer-deps
npm run build                    # (twice only needed on a truly clean checkout)

php artisan migrate --force

# Refresh caches with the new code
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart        # workers reload with new code
php artisan up
```

Quick rollback if a deploy goes wrong: `git checkout <previous-sha>`, re-run
`composer install`, rebuild, `php artisan config:clear && config:cache`, then
`queue:restart`.

> For true zero-downtime, deploy into a fresh release directory and atomically swap a
> `current` symlink (Envoyer/Deployer style), then `queue:restart` and reload PHP-FPM.
> Remember `VITE_REVERB_*` is baked into the build, so a new release must be built with the
> correct env.
