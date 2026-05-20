# WEBTE2 — Octave CAS

Technická dokumentácia k zápočtovému projektu.

## 1. O projekte

Vytvorili sme webovú aplikáciu, ktorá sprístupňuje výpočtový systém **GNU Octave**
cez prehliadač a cez REST API. Aplikácia má tri hlavné časti:

- **Octave konzola** — používateľ píše príkazy Octave priamo v prehliadači,
  so zvýrazňovaním syntaxe a perzistentným pracovným priestorom (premenné ostávajú
  medzi príkazmi).
- **Dve simulácie riadiacich systémov** — inverzné kyvadlo a gulička na tyči.
  Každá simulácia má animáciu (2D, Konva.js) a graf priebehu (Chart.js), ktoré
  sú navzájom **synchronizované** — pri prehrávaní animácie sa po grafe pohybuje
  zvislá čiara označujúca aktuálny snímok.
- **REST API** — typované endpointy s automaticky generovanou OpenAPI
  dokumentáciou (HTML aj PDF na stiahnutie).

Aplikácia je dvojjazyčná (SK/EN), má svetlý aj tmavý režim a je plne
responzívna. Beží celá v Docker kontajneroch.

## 2. Použité technológie

Celá aplikácia je kontajnerizovaná, takže na lokálne spustenie stačí Docker.
Nižšie je zoznam všetkého, čo používame.

### Backend (PHP)

| Technológia | Verzia | Na čo ju používame |
|---|---|---|
| PHP | 8.5 | hlavný jazyk backendu |
| Laravel | 13 | webový framework |
| Inertia.js | 2 | most medzi Laravel backendom a React frontendom (netreba zvlášť API pre stránky) |
| MySQL | 9 | databáza |
| Redis | 7 | cache, session, fronta úloh (cez `predis/predis`) |
| Laravel Horizon | 5 | dashboard a worker pre frontu úloh |
| dedoc/scramble | 0.13 | automatické generovanie OpenAPI špecifikácie z kontrolérov |
| spatie/browsershot | 5 | generovanie PDF (renderuje HTML cez Chromium) |
| spatie/laravel-data | — | typované DTO objekty na hraniciach systému |

### Frontend (JavaScript)

| Technológia | Verzia | Na čo ju používame |
|---|---|---|
| React | 19 | komponenty používateľského rozhrania |
| TypeScript | 5.7 | typovanie frontendu (striktný režim) |
| Tailwind CSS | 4 | štýlovanie |
| Vite | 6 | bundler / dev server |
| CodeMirror 6 | — | editor kódu v Octave konzole (`@uiw/react-codemirror`) |
| Chart.js | 4 | grafy priebehu simulácií |
| Konva.js | 9 | 2D animácie simulácií |
| swagger-ui-react | 5 | zobrazenie OpenAPI dokumentácie |

### Octave bridge (Python)

Octave nespúšťame priamo z PHP. Namiesto toho beží v samostatnom kontajneri
malá HTTP služba napísaná v **Python 3.13** s knižnicou **aiohttp**. Táto
služba prijíma príkazy, spúšťa Octave v izolovanom procese a vracia výstup.
Dôvod je bezpečnosť — Python kontajner nemá prístup na internet a má prísne
limity (CPU, pamäť, počet procesov).

### Nástroje na kontrolu kvality

| Nástroj | Pre čo |
|---|---|
| Pest 4 | PHP testy |
| Vitest | JS/React testy |
| PHPStan (level max) + Larastan | statická analýza PHP |
| Laravel Pint | formátovanie PHP |
| ESLint 9 + Prettier | linting a formátovanie JS/TS |
| ruff + mypy (`--strict`) | linting a typovanie Python služby |

### Infraštruktúra

- **Docker Compose** — celá aplikácia (6 kontajnerov, viď nižšie)
- **nginx** — reverzné proxy pred PHP-FPM
- Na geolokáciu IP adries pre štatistiky používame bezplatnú službu **ip-api.com** (netreba žiadny API kľúč)

## 3. Štruktúra projektu

Stačí vedieť, kde čo zhruba je:

```
app/                  Laravel backend
  Actions/            biznis logika (jedna trieda = jedna akcia)
  Http/Controllers/   kontroléry (API + stránky)
  Jobs/               úlohy spracované vo fronte (napr. generovanie PDF)
  Services/           služby (napr. klient na Octave bridge, geolokácia)
resources/js/         React frontend (stránky, komponenty, hooky, i18n)
docker/               Dockerfile-y a konfigurácie kontajnerov
  octave-bridge/      Python služba na spúšťanie Octave
routes/               definície ciest (web.php, api.php, console.php)
tests/                Pest testy
submission-files/     SQL dump databázy
docker-compose.yml    definícia všetkých kontajnerov
```

## 4. Kontajnery

Aplikácia beží v týchto kontajneroch (`docker-compose.yml`):

| Kontajner | Čo robí |
|---|---|
| `nginx` | reverzné proxy, prijíma HTTP požiadavky |
| `web` | PHP-FPM — synchrónne vybavovanie požiadaviek |
| `cli` | dlhobežiace procesy — Horizon (fronta úloh) a plánovač |
| `mysql` | databáza |
| `redis` | cache, session, fronta |
| `octave-bridge` | Python služba, ktorá spúšťa Octave |

Pomalé alebo plánované veci (generovanie PDF, čistenie starých dát, veľký
CSV export) idú do fronty a spracuje ich `cli` kontajner. Všetko, čo reaguje
na kliknutie používateľa, beží synchrónne v `web` kontajneri.

## 5. Lokálne spustenie

Potrebný je iba **Docker** s **Docker Compose**.

```bash
# 1. Skopírovať konfiguráciu
cp .env.example .env

# 2. Vygenerovať APP_KEY
docker compose run --rm web php artisan key:generate

# 3. Spustiť všetky kontajnery
docker compose up -d --build

# 4. Migrácie databázy
docker compose exec web php artisan migrate --force

# 5. Naplnenie demo dátami (vytvorí demo API kľúč,
#    50 záznamov logov a 100 záznamov štatistík)
docker compose exec web php artisan db:seed --force
```

Aplikácia potom beží na **http://localhost:8080**.

Po seede sa do logu vypíše demo API kľúč — používa sa na volanie REST API
(hlavička `X-API-Key`).

## 6. Konfigurácia (`.env`)

Všetky nastavenia sú v súbore `.env` (vzor je `.env.example`). Najdôležitejšie:

```env
APP_ENV=production            # alebo local pre vývoj
APP_KEY=<vygenerovaný kľúč>
APP_URL=https://...           # URL servera
ASSET_URL=https://...         # musí byť HTTPS URL servera (viď nasadenie)

DB_DATABASE=webte2
DB_USERNAME=webte2
DB_PASSWORD=<heslo>
MYSQL_ROOT_PASSWORD=<root heslo>

REDIS_PASSWORD=<heslo>

CAS_API_KEY_PLAINTEXT=<api kľúč>     # demo API kľúč
HORIZON_ADMIN_TOKEN=<token>         # token na prístup k /horizon dashboardu
```

## 7. Databáza

Schéma databázy je v súbore **`submission-files/webte2-schema.sql`**. Je to
dump štruktúry (bez dát) priamo z MySQL kontajnera po spustení migrácií.
Naimportovaním do prázdnej databázy `webte2` sa znovu vytvorí celá štruktúra.

Migrácie sa za normálnych okolností spúšťajú príkazom `php artisan migrate`
(viď krok 4 vyššie) — SQL súbor je priložený podľa požiadaviek zadania.

## 8. Nasadenie na server

Aplikáciu sme nasadili na školský server **node30.webte.fei.stuba.sk**
(Ubuntu 24.04). Nižšie sú všetky zmeny konfigurácie a doinštalované veci,
ktoré boli oproti lokálnemu spusteniu potrebné.

### 8.1 Doinštalované programy

```bash
snap install docker     # Docker cez Snap (nie cez apt)
apt install nginx       # nginx ako reverzné proxy
```

Docker sme inštalovali cez **Snap**, čo má dve dôležité obmedzenia (viď nižšie).

### 8.2 Umiestnenie projektu

Projekt je naklonovaný do domovského adresára používateľa, **nie** do
`/var/www`, pretože snap Docker nemá prístup k `/var/www`:

```bash
git clone https://github.com/lucasp1337/webte2-cas.git ~/webte2-cas
```

Výsledné umiestnenie:

```
/home/xbrezonak/webte2-cas/
```

### 8.3 Úpravy `docker-compose.yml`

Snap Docker na Ubuntu 24.04 **nepodporuje** tieto direktívy, takže boli
odstránené zo služieb `cli` a `octave-bridge`:

```yaml
# Nefunguje so snap Docker — odstránené:
cap_drop:
  - ALL
security_opt:
  - no-new-privileges:true
```

### 8.4 Úpravy `Dockerfile` — Node.js 20

Tailwind CSS 4 vyžaduje Node.js 20+. Apt na Ubuntu 24.04 dáva iba Node 18,
preto sa Node inštaluje cez NodeSource:

```dockerfile
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y --no-install-recommends nodejs
```

Frontend assets sa buildujú vnútri Docker image (target `web`).

### 8.5 nginx ako reverzné proxy

nginx na serveri presmerúva HTTPS požiadavky na Docker port 8080. Konfigurácia
v `/etc/nginx/sites-available/webte2`:

```nginx
server {
    listen 443 ssl;
    server_name node30.webte.fei.stuba.sk;

    ssl_certificate     /etc/ssl/...;
    ssl_certificate_key /etc/ssl/...;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Keďže TLS terminuje nginx, v `.env` musí byť `ASSET_URL` nastavené na HTTPS
URL servera — inak prehliadač blokuje načítanie assetov (mixed content).

### 8.6 logrotate pre Laravel logy

Adresár `storage/logs` nesmie byť group-writable, inak ho logrotate odmietne
rotovať. Nastavíme mu práva `755`:

```bash
sudo docker compose exec web chmod 755 storage/logs
```

Potom vytvoríme súbor `/etc/logrotate.d/laravel-webte2`, ktorý rotuje Laravel
logy denne a drží 7 dní histórie:

```
/home/xbrezonak/webte2-cas/storage/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    copytruncate
}
```

Dôležité body:

- logrotate beží ako **root** (cez systémový cron) — root prejde cez domovský
  adresár `/home/xbrezonak` (ten má práva `750`) aj cez bind-mount projektu.
  Preto v configu **nie je** `su` direktíva.
- `storage/logs` musí mať práva `755` (nie group-writable) — logrotate inak
  rotáciu odmietne s chybou o "insecure permissions".
- `copytruncate` — logrotate súbor skopíruje a pôvodný vyprázdni na mieste,
  takže `laravel.log` si zachová vlastníka `www-data`, pod ktorým beží PHP-FPM
  v kontajneri. Keby logrotate namiesto toho vytváral nový súbor (`create`),
  ten by patril inému používateľovi a PHP-FPM by doň nedokázal zapisovať —
  výsledkom by bolo HTTP 500 na každej požiadavke zapisujúcej do logu.

### 8.7 Oprava `UserFactory` pre produkciu

V `database/factories/UserFactory.php` sme nahradili globálny helper `fake()`
za `$this->faker`, pretože `fake()` nie je dostupný mimo testovacieho prostredia.

### 8.8 Spustenie na serveri

```bash
cd ~/webte2-cas
sudo docker compose up -d --build
sudo docker compose exec web php artisan migrate --force
sudo docker compose exec web php artisan db:seed --force
```

## 9. Rozdelenie práce

Na projekte sme pracovali dvaja. Prácu sme rozdelili podľa vrstiev — jeden
robil backend, druhý frontend. Keďže väčšina funkcií má backendovú aj
frontendovú časť, prakticky každú funkciu sme robili spoločne, len každý
svoju vrstvu.

### Lucas Palka — backend a infraštruktúra

- **Docker Compose stack a Dockerfile** — návrh všetkých kontajnerov,
  multi-stage build, izolácia a limity kontajnerov.
- **Python Octave bridge** — HTTP služba v aiohttp, ktorá spúšťa Octave
  v izolovanom procese, sanitizácia príkazov, perzistencia pracovného priestoru.
- **Backend základ** — autentifikácia cez API kľúče, logovanie všetkých
  požiadaviek, udalosti (events) a observery, rate limiting.
- **Octave konzola — backend** — vykonanie príkazu cez bridge, správa session.
- **Simulácie — backend** — inverzné kyvadlo aj gulička na tyči: kontroléry,
  akcie, generovanie Octave skriptov a parsovanie výsledkov trajektórie.
- **OpenAPI dokumentácia a asynchrónny PDF export** — generovanie špecifikácie,
  generovanie PDF cez frontu úloh.
- **Štatistiky — backend** — anonymné sledovanie použitia riadené udalosťami,
  geolokácia IP adries.
- **Záznamy (Logs) a CSV export — backend** — API endpointy, synchrónny aj
  asynchrónny CSV export cez frontu úloh.

### Samuel Brezoňák — frontend a nasadenie

- **Nasadenie na server** node30.webte.fei.stuba.sk — inštalácia a konfigurácia
  (Docker cez Snap, nginx reverzné proxy, logrotate, úpravy popísané v kapitole 8).
- **Frontend základ** — nastavenie React + Inertia, dizajnový systém,
  dvojjazyčnosť (SK/EN), svetlý/tmavý režim, spoločné komponenty.
- **Octave konzola — frontend** — editor kódu (CodeMirror), zobrazenie výstupu,
  panel s premennými.
- **Simulácie — frontend** — inverzné kyvadlo aj gulička na tyči: 2D animácie
  (Konva.js), grafy priebehu (Chart.js), formuláre na zadanie parametrov.
- **Stránka záznamov a štatistík — frontend** — tabuľka requestov s filtrami
  a stránkovaním, grafy a tabuľky štatistík.
- **Swagger UI stránka** — zobrazenie OpenAPI dokumentácie v prehliadači.

Všetky úlohy zo zadania boli dokončené.
