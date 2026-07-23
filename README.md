# PBXPro

PBXPro is a multi-tenant PBX and contact-center foundation built with Laravel 12, PostgreSQL, Redis, React, TypeScript, Laravel Reverb, and FreeSWITCH. It contains separate Super Admin, Tenant Admin, and Agent experiences using the supplied PBXPro dashboard design.

## Current implementation

This repository implements the first production slice:

- Responsive Super Admin, Tenant Admin, and Agent interfaces
- Sanctum session authentication with role-locked production UI
- Transactional Super Admin onboarding that creates a tenant and its initial Tenant Admin together
- Database-backed tenant list, tenant suspension/reactivation, live dashboard counts, and sign-out
- Tenant Admin extension provisioning/editing with range/capacity enforcement, generated credentials, and encrypted SIP secrets
- Tenant Admin agent provisioning with portal credentials and optional extension assignment
- Tenant-aware models, middleware, queries, validation, and WebSocket channel authorization
- Tenants, agents, extensions, extension devices/sessions, queues, DIDs, IVR versions, calls, legs, events, recordings, commands, and audit schema
- Dynamic FreeSWITCH directory lookup through `mod_xml_curl`
- Persistent `php artisan telephony:esl` worker with reconnect/backoff and Redis command consumption
- Internal extension dialplan example
- PostgreSQL tenant-isolation integration tests
- Nginx, systemd, FreeSWITCH, and logrotate templates for Debian 12

Outbound campaigns, lead imports, the complete IVR runtime, recording post-processing, and progressive dialing remain later phases. Except for Tenants, Extensions, and Agents, the management menus still preview their intended design and must not be treated as completed workflows.

## Architecture

```text
Browser (React)
  |-- HTTPS / Sanctum --> Laravel API --> PostgreSQL
  |                              |-----> Redis / Queue
  |<-- Reverb WebSocket ---------|
  |-- SIP WSS / RTP -------------------> FreeSWITCH
                                         ^
Laravel API --> Redis command list --> ESL worker
FreeSWITCH --> XML Curl ------------> Laravel XML endpoint
```

The Agent browser never connects to ESL. Only SIP/WebRTC signaling and media may connect directly to FreeSWITCH.

## Repository layout

```text
backend/              Laravel API, migrations, tests, ESL worker
src/                  React/TypeScript application
deploy/nginx/         HTTPS, SPA, API, and Reverb proxy template
deploy/systemd/       Queue, Reverb, ESL, and scheduler units
deploy/freeswitch/    XML Curl, ESL, and internal dialplan templates
deploy/logrotate/     Laravel log rotation
```

## Debian 12 production installation

The commands below assume:

- A clean Debian 12 VM and a root shell (`su -`)
- Windows host IP `192.168.56.1`
- Debian PBXPro VM IP `192.168.56.105`
- Local domain `pbxpro.test`, mapped to `192.168.56.105` in the Windows hosts file
- Project files authored under `DialerApp` on Windows and transferred to `/var/www/pbxpro` with WinSCP
- Repository installed at `/var/www/pbxpro`
- One-server launch topology with services bound locally where possible
- A SignalWire Personal Access Token for official FreeSWITCH binary packages

This guide consistently uses `pbxpro.test` for the local VM. Do not run Certbot for this name; `.test` uses a locally trusted mkcert certificate. Replace every `CHANGE_ME` value and all example passwords before starting services.

### 1. Update Debian and install base services

```bash
apt update
apt full-upgrade -y
apt install -y ca-certificates curl git gnupg2 lsb-release unzip nginx \
  postgresql postgresql-contrib redis-server \
  nftables
systemctl enable --now postgresql redis-server nginx nftables
```

### 2. Install PHP 8.4

Debian 12 ships an older PHP branch, so bootstrap the maintained Debian PHP repository before requesting any `php8.4-*` packages:

```bash
apt update
apt install -y ca-certificates curl lsb-release
curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
dpkg -i /tmp/debsuryorg-archive-keyring.deb
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ bookworm main" \
  | tee /etc/apt/sources.list.d/php.list
apt update
apt-cache policy php8.4-cli
apt install -y php8.4-cli php8.4-fpm php8.4-pgsql php8.4-redis \
  php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath \
  php8.4-intl php8.4-opcache
php -v
composer --version
```

Do not run the PHP package installation until `apt-cache policy php8.4-cli` shows a candidate from `https://packages.sury.org/php bookworm/main`.

Install the current Composer release from the official installer. This avoids compatibility warnings from Debian's older Composer package under PHP 8.4:

```bash
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
test "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM" || { echo 'Invalid Composer installer'; rm -f /tmp/composer-setup.php; exit 1; }
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php
hash -r
composer --version
```

### 3. Install Node.js 22 and pnpm

Node is used only to compile the frontend; it does not need to run in production.

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
npm install -g pnpm@11
node --version
pnpm --version
```

### 4. Install the application source

If the source is hosted in Git:

```bash
git clone YOUR_REPOSITORY_URL /var/www/pbxpro
cd /var/www/pbxpro
```

If you copied the `DialerApp` project folder directly to `/root/DialerApp`, do not run `git clone`. Copy its contents while excluding local build artifacts:

```bash
apt install -y rsync
mkdir -p /var/www/pbxpro
rsync -a \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='node_modules' \
  --exclude='dist' \
  /root/DialerApp/ /var/www/pbxpro/
chown -R root:www-data /var/www/pbxpro
cd /var/www/pbxpro
```

If `DialerApp` is stored somewhere else, replace `/root/DialerApp/` with its actual absolute path. The trailing `/` is important: it copies the folder contents rather than creating `/var/www/pbxpro/DialerApp`.

Verify the resulting layout:

```bash
test -f /var/www/pbxpro/package.json
test -f /var/www/pbxpro/backend/composer.json
test -d /var/www/pbxpro/src
test -d /var/www/pbxpro/deploy
find /var/www/pbxpro -maxdepth 2 -type f | sort | head -30
```

### 5. Create PostgreSQL database and user

Generate a strong database password, then run:

```bash
runuser -u postgres -- psql <<'SQL'
CREATE USER pbxpro WITH PASSWORD 'CHANGE_ME_DATABASE_PASSWORD';
CREATE DATABASE pbxpro OWNER pbxpro ENCODING 'UTF8';
REVOKE ALL ON DATABASE pbxpro FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE pbxpro TO pbxpro;
SQL
```

Keep PostgreSQL and Redis bound to loopback for the single-server deployment. Do not expose ports `5432` or `6379` publicly.

### 6. Configure and install Laravel

```bash
cd /var/www/pbxpro/backend
cp .env.example .env
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache resources/views
id pbxpro-deploy >/dev/null 2>&1 || useradd --system --create-home --shell /bin/bash --groups www-data pbxpro-deploy
chown -R pbxpro-deploy:www-data /var/www/pbxpro
runuser -u pbxpro-deploy -- composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
```

Edit `/var/www/pbxpro/backend/.env` and set at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pbxpro.test
FRONTEND_URL=https://pbxpro.test
DB_PASSWORD=CHANGE_ME_DATABASE_PASSWORD
SESSION_DOMAIN=.pbxpro.test
SANCTUM_STATEFUL_DOMAINS=pbxpro.test,*.pbxpro.test
REVERB_APP_KEY=CHANGE_ME_RANDOM_KEY
REVERB_APP_SECRET=CHANGE_ME_RANDOM_SECRET
REVERB_HOST=pbxpro.test
FREESWITCH_ESL_PASSWORD=CHANGE_ME_ESL_PASSWORD
FREESWITCH_XML_TOKEN=CHANGE_ME_XML_TOKEN
PBXPRO_ADMIN_EMAIL=admin@your-domain.example
PBXPRO_ADMIN_PASSWORD=CHANGE_ME_LONG_INITIAL_PASSWORD
```

Use separate randomly generated values. For example:

```bash
openssl rand -hex 32
```

Run migrations, create the initial Super Admin, and cache production configuration:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
chown -R www-data:www-data /var/www/pbxpro/backend/storage /var/www/pbxpro/backend/bootstrap/cache
find /var/www/pbxpro/backend/storage /var/www/pbxpro/backend/bootstrap/cache -type d -exec chmod 775 {} \;
```

Log in once and change the seeded administrator password immediately. Remove `PBXPRO_ADMIN_PASSWORD` from `.env` after seeding.

### 7. Compile the React application

```bash
cd /var/www/pbxpro
cp .env.example .env
sed -i 's/VITE_DEMO_MODE=false/VITE_DEMO_MODE=false/' .env
pnpm install --frozen-lockfile
pnpm build
mkdir -p /var/www/pbxpro/frontend
cp -a dist/. /var/www/pbxpro/frontend/
chown -R www-data:www-data /var/www/pbxpro/frontend
```

`VITE_DEMO_MODE=false` is required in production. Setting it to `true` enables the visual role switcher and mock dashboard data for UI demonstrations.

### 8. Configure local TLS and install Nginx

Let’s Encrypt cannot issue certificates for `.test`, private IP addresses, or names created only in a Windows hosts file. Generate a locally trusted certificate on the Windows host with `mkcert`:

```powershell
winget install FiloSottile.mkcert

# Close PowerShell after WinGet finishes, then reopen it as Administrator.
# Verify that the new shell can find mkcert:
where.exe mkcert
mkcert -install

New-Item -ItemType Directory -Force C:\pbxpro-certs
Set-Location C:\pbxpro-certs
mkcert -cert-file pbxpro.test.pem -key-file pbxpro.test-key.pem "pbxpro.test" "*.pbxpro.test"
```

If a newly opened PowerShell still cannot find `mkcert`, locate WinGet's portable executable and run it directly:

```powershell
$mkcert = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "mkcert*.exe" -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1

$mkcert.FullName
& $mkcert.FullName -install
```

Add the VM address to `C:\Windows\System32\drivers\etc\hosts` using an Administrator editor:

```text
192.168.56.105 pbxpro.test
192.168.56.105 abcfinance.pbxpro.test
```

Copy both generated files to the Debian VM at `192.168.56.105` with WinSCP, then:

```bash
mkdir -p /etc/nginx/ssl
cp /root/pbxpro.test.pem /etc/nginx/ssl/pbxpro.test.pem
cp /root/pbxpro.test-key.pem /etc/nginx/ssl/pbxpro.test-key.pem
chmod 644 /etc/nginx/ssl/pbxpro.test.pem
chmod 600 /etc/nginx/ssl/pbxpro.test-key.pem
cp /var/www/pbxpro/deploy/nginx/pbxpro.conf /etc/nginx/sites-available/pbxpro
ln -sfn /etc/nginx/sites-available/pbxpro /etc/nginx/sites-enabled/pbxpro
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx php8.4-fpm
```

Ensure `/etc/hosts` maps the application domains to loopback so local FreeSWITCH XML Curl requests satisfy the Nginx allowlist:

```text
127.0.0.1 pbxpro.test
127.0.0.1 abcfinance.pbxpro.test
```

Verify from Windows. Schannel may require best-effort revocation because a local mkcert CA has no public CRL/OCSP service:

```powershell
curl.exe --ssl-revoke-best-effort https://pbxpro.test/up
```

If the installed Windows curl does not support that option, use `--ssl-no-revoke` for this local `.test` certificate only. Do not disable revocation checking for public production certificates.

### 9. Install FreeSWITCH from the official repository

Official binary packages require a SignalWire Personal Access Token. Create one in your SignalWire account, then enter it without echoing it:

```bash
read -rsp 'SignalWire personal access token: ' TOKEN
echo
apt install -y gnupg2 wget lsb-release
wget --http-user=signalwire --http-password="$TOKEN" \
  -O /usr/share/keyrings/signalwire-freeswitch-repo.gpg \
  https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg
echo "machine freeswitch.signalwire.com login signalwire password $TOKEN" \
  | tee /etc/apt/auth.conf.d/freeswitch.conf >/dev/null
chmod 600 /etc/apt/auth.conf.d/freeswitch.conf
echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ bookworm main" \
  | tee /etc/apt/sources.list.d/freeswitch.list
apt update
apt install -y freeswitch-meta-vanilla freeswitch-sounds-en-us-callie freeswitch-sounds-music \
  freeswitch-mod-xml-curl freeswitch-mod-callcenter freeswitch-mod-event-socket
unset TOKEN
```

The official FreeSWITCH guide documents the authenticated repository and package layout: <https://developer.signalwire.com/freeswitch/foundations/getting-started/>.

### 10. Configure FreeSWITCH integration

FreeSWITCH calls the local PBXPro HTTPS endpoint. For the Windows mkcert development certificate, copy `%LOCALAPPDATA%\mkcert\rootCA.pem` to `/usr/local/share/ca-certificates/pbxpro-mkcert-ca.crt` on Debian, then:

```bash
update-ca-certificates
curl -fsS https://pbxpro.test/up >/dev/null
```

Never copy `rootCA-key.pem`; only the public `rootCA.pem` belongs on the VM.

Back up the original files and install the templates. The secrets are read from the existing Laravel environment without printing them:

```bash
cp /etc/freeswitch/autoload_configs/xml_curl.conf.xml{,.bak}
cp /etc/freeswitch/autoload_configs/event_socket.conf.xml{,.bak}
cp /var/www/pbxpro/deploy/freeswitch/xml_curl.conf.xml /etc/freeswitch/autoload_configs/xml_curl.conf.xml
cp /var/www/pbxpro/deploy/freeswitch/event_socket.conf.xml /etc/freeswitch/autoload_configs/event_socket.conf.xml
cp /var/www/pbxpro/deploy/freeswitch/pbxpro-internal.xml /etc/freeswitch/dialplan/pbxpro-internal.xml
xml_token=$(sed -n 's/^FREESWITCH_XML_TOKEN=//p' /var/www/pbxpro/backend/.env)
esl_password=$(sed -n 's/^FREESWITCH_ESL_PASSWORD=//p' /var/www/pbxpro/backend/.env)
sed -i "s/CHANGE_ME_XML_TOKEN/$xml_token/g" /etc/freeswitch/autoload_configs/xml_curl.conf.xml
sed -i "s/CHANGE_ME_ESL_PASSWORD/$esl_password/g" /etc/freeswitch/autoload_configs/event_socket.conf.xml
unset xml_token esl_password
chown -R freeswitch:freeswitch /etc/freeswitch
```

PBXPro binds XML Curl only to the dynamic directory. The internal dialplan remains local in `pbxpro-internal.xml`; binding XML Curl to `dialplan` would replace the local dialplan completely.

Confirm these modules are installed and enabled in `/etc/freeswitch/autoload_configs/modules.conf.xml`:

```xml
<load module="mod_sofia"/>
<load module="mod_event_socket"/>
<load module="mod_xml_curl"/>
<load module="mod_callcenter"/>
<load module="mod_commands"/>
```

Keep ESL on `127.0.0.1:8021`; never expose it to the Internet. Restart and inspect FreeSWITCH:

```bash
systemctl enable --now freeswitch
systemctl restart freeswitch
fs_cli -x 'status'
fs_cli -x 'module_exists mod_xml_curl'
fs_cli -x 'module_exists mod_callcenter'
```

### 11. Install PBXPro background services

```bash
cp /var/www/pbxpro/deploy/systemd/pbxpro-*.service /etc/systemd/system/
cp /var/www/pbxpro/deploy/logrotate/pbxpro /etc/logrotate.d/pbxpro
systemctl daemon-reload
systemctl enable --now pbxpro-queue pbxpro-reverb pbxpro-esl pbxpro-scheduler
```

Check all application services:

```bash
systemctl --no-pager --full status php8.4-fpm nginx postgresql redis-server freeswitch \
  pbxpro-queue pbxpro-reverb pbxpro-esl pbxpro-scheduler
journalctl -u pbxpro-esl -n 100 --no-pager
```

### 12. Firewall and network ports

Permit only the ports you actively use:

| Port | Protocol | Purpose |
|---|---|---|
| 22 | TCP | SSH, restricted to administrator IPs |
| 80, 443 | TCP | HTTP redirect, HTTPS API/UI/Reverb |
| 5060 | UDP/TCP | SIP, preferably restricted to carrier/device networks |
| 5061 | TCP | SIP TLS |
| 7443 | TCP | SIP secure WebSocket if FreeSWITCH terminates WSS |
| 16384–32768 | UDP | RTP media range; confirm against `switch.conf.xml` |

Do not expose PostgreSQL `5432`, Redis `6379`, PHP-FPM, Reverb `8080`, or ESL `8021`.

## Onboard the first tenant

1. Open `https://pbxpro.test` and sign in as the seeded Super Admin.
2. Open **Tenants** and select **Onboard tenant**.
3. Create tenant `ABC Finance` with code `abcfinance`, SIP domain `abcfinance.pbxpro.test`, extension range `1000–1999`, capacity limits, and its initial Tenant Admin credentials.
4. Submit the form. Tenant and administrator creation is one database transaction; validation failure creates neither record.
5. Use the top-bar sign-out button, then sign in with the new Tenant Admin account.

## Provision the first extensions and agents

1. Sign in at `https://abcfinance.pbxpro.test` as the ABC Finance Tenant Admin.
2. Open **Extensions**, select **Add extension**, and create extensions `1001` and `1002`. The form generates a different secure SIP password for each extension; copy each password before saving.
3. Open **Agents**, select **Add agent**, create each agent's portal login, and assign an available extension.
4. Verify that the Extensions screen shows the assigned user and that the Agents screen shows the assigned extension.

SIP passwords are encrypted at rest and are never returned by the API after creation. Store each password securely when it is generated. Editing an extension keeps the existing secret by default; use **Generate new SIP password** only when intentionally rotating device credentials.

After extensions `1001` and `1002` exist, point the SIP domain to the VM and configure the clients on port `5060` or TLS port `5061`.

Confirm registrations:

```bash
fs_cli -x 'show registrations'
fs_cli -x 'sofia status profile internal reg'
```

Dial `1002` from `1001`. Inspect application and FreeSWITCH logs if routing fails:

```bash
tail -f /var/www/pbxpro/backend/storage/logs/laravel.log
journalctl -fu pbxpro-esl
fs_cli
```

## Tests

Run backend tests before a production update:

```bash
cd /var/www/pbxpro/backend
composer install
php artisan test
```

Build the frontend:

```bash
cd /var/www/pbxpro
pnpm install --frozen-lockfile
pnpm build
```

The source was frontend-build verified during development. PHP/FreeSWITCH integration tests must run on the Debian VM because PHP and FreeSWITCH are not installed in the source workspace.

## Production update procedure

```bash
cd /var/www/pbxpro
git pull --ff-only
pnpm install --frozen-lockfile
pnpm build
rm -rf /var/www/pbxpro/frontend/*
cp -a dist/. /var/www/pbxpro/frontend/
cd backend
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
systemctl restart php8.4-fpm pbxpro-queue pbxpro-reverb pbxpro-esl pbxpro-scheduler
```

Back up PostgreSQL and recordings before migrations. Keep `.env`, SIP credentials, trunk credentials, ESL passwords, XML tokens, and recordings outside source control.

## Operational checks

```bash
curl -fsS https://pbxpro.test/up
redis-cli ping
runuser -u postgres -- psql -d pbxpro -c 'select now();'
fs_cli -x 'status'
fs_cli -x 'show registrations'
systemctl is-active pbxpro-queue pbxpro-reverb pbxpro-esl pbxpro-scheduler
```

Laravel must be served through `backend/public`, never from the backend project root. See the official Laravel installation guidance: <https://laravel.com/docs/12.x/installation>.
