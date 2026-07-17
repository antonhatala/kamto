# Technické poznámky a pasti

Kód je záměrně **bez komentářů** (viz Konvence v `CLAUDE.md`). Invarianty a pasti, na které
je při úpravách potřeba myslet, žijí tady — ne ve zdrojácích. Při změně chování udržuj
tenhle soubor v syncu.

## Migrace

- Migrace **nesmí obsahovat `BEGIN`/`COMMIT`** — transakci (včetně rollbacku částečně
  proběhlé migrace) řídí `MigrationRunner`.
- Číslování `NNN_nazev.sql`, aplikují se v abecedním pořadí; stav v tabulce `_migration`
  (runner si ji vytváří sám), druhý běh je no-op.
- SQLite `ALTER TABLE DROP COLUMN` funguje (3.35+), ale jen mimo indexy/constrainty.
- Produkce: `docker/entrypoint.prod.sh` migruje volume DB při každém startu (idempotentní).

## DB a repozitáře

- **Peníze = integer haléře** (CZK×100), formátování až při zobrazení (`Money`, filtr `czk`).
- `created_at` si generují repozitáře samy (`date(DATE_ATOM)`) — volající ho neposílá.
- `payment` má `UNIQUE(service_id, period_year, period_month)`; `period_month` je NOT NULL
  i pro roční služby (roční se eviduje na svůj `due_month`).
- `PaymentRepository::insertIgnore()` = `ON CONFLICT DO NOTHING`: idempotentní lazy vznik
  řádku i při souběhu dvou akcí. Existující řádek se **nikdy nepřepisuje** — chrání snapshot
  `amount`/`due_date` pořízený při prvním vzniku (pozdější změna `service.amount` se do
  existujících plateb nepropisuje).
- `PaymentService::upsert()`: roční služba dostane `period_month = due_month` bez ohledu na
  předaný měsíc (obranná invariance); klouzavá služba (`is_sliding`) má `due_day` jen jako
  placeholder 1 a `due_date` se dopočítává na poslední den měsíce.
- Zaplaceno a přeskočeno jsou **vzájemně výlučné** — pozitivní přechod (markPaid/skip)
  vždy vynuluje opačný příznak.
- Klouzavá služba **nikdy nespadne do „Po splatnosti"** (`PaymentStatus::derive` Overdue
  u `is_sliding` nederivuje) — na dashboardu místo toho dostává akcentní (copper) zvýraznění,
  dokud čeká na zaplacení; datum splatnosti se u ní nezobrazuje (bylo by nepravdivé).
- Platební akce se smí týkat jen **aktivní** služby — archivovaná/neexistující → výjimka,
  presenter ji promítá na 404 (obrana proti crafted POST signálu → tichý insert řádku).
- Automatické řazení služeb: `is_sliding, due_day, id` — `findAll()` i `findArchived()`
  musí řadit stejně; ruční řazení neexistuje.
- Heatmapa (`PaymentCell::build`): **existující payment řádek je ground truth** — stav buňky
  se odvozuje z něj bez ohledu na aktuální `period`/`due_month` služby. Jinak by „osiřelá"
  roční platba (perioda změněná po zaplacení) zmizela z heatmapy jako Inactive, ale v seznamu
  plateb detailu zůstala (nesoulad na jedné stránce). Kryto `PaymentCellTest`/`YearHeatmapTest`.
- `DueDateCalculator`: dny v měsíci přes `DateTimeImmutable` („last day of this month") nad
  skutečným kalendářem — řeší i stoletou výjimku (2100 není přestupný) a nezávisí na
  `ext-calendar` (v image není).

## Nette / presentery

- Stavové akce = jen POST signály (`#[Requires(methods: 'POST')]`) — automatická
  same-origin ochrana Nette pro `handle*` metody.
- Dashboard amount-form (Multiplier): prefill mapy (`servicesById`/`paymentsByServiceId`)
  plní až `renderDefault()`; na POST submitu jsou prázdné → fallback prefill, odeslaná data
  ho stejně přepíšou. Nejde o bug.
- Dialog „Zaplatit jinou částku": potvrzení dělá `setAmount` **a hned** `markPaid` — uživatel
  po potvrzení částky čekal zaplaceno a zapomínal klikat „Zaplaceno ✓" zvlášť. Úprava částky
  bez zaplacení neexistuje (výchozí částku mění edit služby).
- Povinnost `due_day`/`due_month` se vynucuje až v `serviceFormSucceeded()`, ne pravidly na
  formuláři — progressive disclosure je čisté CSS (`:has()`), klient nemusí mít JS a server
  je zdroj pravdy. Pozn.: Nette `Rules::validate()` přeskakuje pravidla nevyplněného
  nepovinného pole (proto klouzavá služba projde bez `due_day`).
- Detail služby používá `find()` (ne `findActive()`) — historie archivované služby musí
  zůstat čitelná.
- CSV export: `getHttpResponse()` vrací rozhraní `IResponse` (bez `sendAsFile()`), proto se
  `Content-Disposition` nastavuje přímo přes `setHeader()`.

## Latte pasti

- `{import '../_heatmap.latte'}` musí být **uvnitř** `{block content}` — s layoutem se
  top-level kód šablony před renderem bloku nespustí („undefined block").
- Dynamický hex ve `style` atributu vyžaduje `|noescape` (Latte escapuje `#` na `\#` →
  neplatné CSS). Bezpečné **jen** pro hodnoty prošlé `CategoryDisplay::resolve()`
  (re-validace regexem + neutrální fallback) nebo statické literály.
- `category_id` může být NULL — nesahat s NULL klíčem přímo do pole (deprecated v PHP),
  vždy přes ternár.

## UI / šablony — drobné pasti

- Flash zprávy: centralizovaně je vykresluje `@layout` — ale jen pro přihlášený chrome.
  `Sign/in` si flashe (např. po odhlášení) vykresluje sám uvnitř karty.
- Hlavička je mobile-first: logo + Odhlásit se na prvním řádku, navigace se zabalí na vlastní
  řádek (`order` utility, `w-full`) — tělo stránky nesmí nikdy scrollovat vodorovně.
- `<summary>` s `display: inline-flex` ruší nativní `::marker` — šipku dodává vlastní span
  s otočením přes `group-open`.
- Roční graf (Overview): neviditelný `<rect>` přes celý sloupec = větší zásahová plocha pro
  hover/title, není to mrtvý kód.

## CSV export

- CZ Excel: středník jako oddělovač, UTF-8 **BOM** (jinak Excel hádá kódování), **CRLF**
  za každým řádkem vč. posledního (RFC 4180).
- Formula injection (OWASP): buňka začínající `=`/`+`/`-`/`@`/tab/CR dostane prefix `'`.
  Aplikuje se na **každou** buňku (`escapeCell()`), protože jména služeb/kategorií jsou
  uživatelský vstup. Pořadí: prefix `'` nikdy sám nevyvolá RFC-4180 quotování.

## Service worker (bezpečnostně citlivé)

- Cache-first **jen** statické same-origin GET bez query (`/css/`, `/js/`, `/icons/`,
  `/manifest.json`). HTML se **nikdy** necachuje — navigace jsou network-only + offline
  fallback (`offline.html`). Non-GET / cross-origin / cokoli s query se **neintercepuje**
  (login/CSRF POST, `Set-Cookie`, CSV export `?year=`).
- **`CACHE_VERSION` bumpni při KAŽDÉ změně app shell assetů** (CSS, JS, ikony, manifest,
  offline.html) — jinak prohlížeče drží starou verzi donekonečna. Precache jede
  s `{cache: 'reload'}` (obchází HTTP cache).
- Precache záměrně dvouúrovňový: `CORE_ASSETS` přes `cache.addAll` (když jeden 404, install
  selže — cache nesmí být poloprázdná), `ICON_ASSETS` přes `Promise.allSettled` (best-effort,
  chybějící PNG nesmí shodit install).
- E2E suite service workery blokuje (`serviceWorkers: 'block'`) kromě `80-pwa` — stale-cache
  problémy se v testech neprojeví, projeví se u uživatele.

## CSP

- Produkční hlavička v `config/config.neon`: `script-src 'self'` → **žádný inline
  `<script>` v šablonách** (veškerý JS ve `www/js/app.js`; jediný inline je Tracy debug bar,
  v produkci vypnutý). `style-src 'unsafe-inline'` kvůli inline hexům kategorií/heatmapy.
- Dev vypnutí CSP jen přes verzovaný `config/config.dev.neon`, který Bootstrap načítá pouze
  v debug módu (`APP_ENV`) — fail-closed, nezávisí na přítomnosti gitignored souboru.
- Produkční DI kontejner se nerefreshuje na změnu configu — image má prázdný `temp/cache`,
  kompiluje se čerstvě.

## Ikony a značka

- Logotyp „kamto" + tečky-měsíce (návrh 13): plné tečky z whitelistu
  `CategoryPresenter::Palette`, čárkovaná pauza accent-300. Stejný lockup: ikony, hlavička,
  login, offline.html.
- SVG předlohy ikon mají logotyp v `<text>` → **rasterizace vyžaduje font Inter**
  (`apt-get install fonts-inter` v Playwright kontejneru, fallback Liberation Sans);
  PNG se commitují, žádná runtime závislost.
- `apple-touch-icon.png` se rasterizuje z **maskable** předlohy (full-bleed) — iOS neumí
  průhledné rohy (dá za ně černou). Maskable: obsah v 80% safe-zone (poloměr 205 px z 512).
- Favicon = glyf na **průhledném** pozadí — Safari kreslí tmavý obrys kolem favicon se
  světlou neprůhlednou destičkou. PNG, ne SVG (text by závisel na fontech prohlížeče).
  Wordmark ve faviconě je accent-600 (o stupeň světlejší než accent-700 v ikoně), ať zůstane
  čitelný i na tmavém tab baru. Safari drží favicony ve vlastní per-doména cache (sdílené
  i s anonymním oknem).

## Tailwind v4 pasti

- Preflight nuluje margin všech prvků → `<dialog>` ztrácí UA centrování; vrací ho base
  pravidlo `dialog { margin: auto }`. Centrování hlídá assert v `60-dashboard.spec.js`.
- Tlačítka mají ve v4 `cursor: default` (změna proti v3) → base pravidlo
  `button:not(:disabled) { cursor: pointer }`.
- V CSS komentářích nikdy nepiš sekvenci `*/` (např. `h-*/w-*`) — ukončí komentář a build
  spadne. (Po zákazu komentářů spíš historická poznámka.)
- `.avatar-initial`: tint z `--c` přes `color-mix` (podklad 16 % do bílé, text 62 % do
  stone-900 — ověřeno ≥4.5:1 pro celý whitelist); bez `--c` fallback stone-400. Stavové
  utility (bg-red-100, opacity-60) tint přebíjejí — stav > dekorace.

## E2E harness

- `reset-db.js` **maže a znovu vytváří `var/kamto.db`** (a login throttle) — lokální data
  před během zálohovat. Migrace aplikuje přes `node:sqlite` ve stejném pořadí jako runner.
- Jeden worker, specy běží v pořadí dle číselného prefixu — sdílená DB a globální login
  throttle, CRUD specy na sebe navazují.
- `BASE_URL=http://localhost:80` + `network_mode: "service:nginx"`: service worker vyžaduje
  secure context a Chromium ho dává jen pro localhost — compose hostname (`nginx`) by SW
  tiše vypnul.
- Když host port 8080 drží jiný projekt, e2e lze pustit s override souborem
  (`ports: !reset` → jiný port) — viz CLAUDE.md Lokální vývoj.
- `helpers.parseColor()`: Chromium vrací vestavěné Tailwind barvy (oklch definice) zpět
  v `oklch()`, jen custom hex/rgb tokeny (accent-*) jako `rgb()` — proto parser umí obojí
  a asserty se píší sémanticky (lightness/hue), ne na přesný zápis.
- Playwright past: `getByRole('listitem').locator('a').first()` zúží na první anchor přes
  CELÝ seznam, ne per položku — per-item lokátory skládat od konkrétního listitem.

## Deploy / provoz

- Magic Containers tahají image jen z DockerHubu/GitHubu → GHCR (ne GitLab registry).
  Bunny action pinnutá na commit SHA.
- Server blok `docker/nginx.prod.conf` držet v syncu s dev `docker/nginx.conf`.
- Session soubory na volume `var/sessions`, životnost 14 dní sliding; login throttle stav
  v `temp/login-throttle.json`.
- `offline.html` musí zůstat soběstačná (inline CSS, žádná síť, žádná autentizace).
- libSQL (`tursodatabase/turso-client-php`) je odložený — artefakt pro PHP 8.5 neexistuje;
  proto `phpstan.neon` používá `scanFiles` se stubem `stubs/LibSQL.stub.php` místo reálné
  extension (konstruktor `LibsqlDb` má guard s jasnou výjimkou). `LibsqlDb::transaction()`
  jede přes prosté `BEGIN`/`COMMIT`/`ROLLBACK` na hlavním spojení, ne přes objekt
  `\LibSQLTransaction` (nedoloženo, že umí `query()`) — až extension přibude, ověřit.
  Instalace se pak řeší v Dockerfile.

## Produkční hardening (neměnit bez rozmyslu)

- `docker/php-fpm.prod.conf`: fpm poslouchá na **127.0.0.1:9000** — FastCGI nesmí být
  dosažitelné ze sítě (jinak neautentizované spuštění PHP → RCE).
- `docker/nginx.prod.conf`: **`fastcgi_param HTTPS on;` je kritický** — bez něj Nette
  (`isSecured()` = false) skládá `http://` odkazy, 302 propadne na http a prohlížeč zahodí
  Secure session cookie → rozbitý login v produkci.
- HSTS: záměrně jen `max-age` (bez `includeSubDomains`/`preload`), jen v produkci.
- `docker/php.ini`: `cgi.fix_pathinfo = 0` (klasický nginx+fpm RCE vektor);
  `output_buffering = 4096` kvůli pořadí session/hlaviček v Nette/Latte;
  `expose_php = Off` + potlačení `X-Powered-By` (defense-in-depth, verzi PHP neprozrazovat).
- `.dockerignore`: **nesmí obsahovat** `src/`, `package.json` ani `composer.json` — filtruje
  build kontext pro VŠECHNY stage, assets/vendor stage by přišly o svoje COPY.
- Adminer (dev): DB volume je mountnutý **mimo docroot** — `php -S` Admineru by jinak
  ochotně servíroval `kamto.db` jako statický download. Image pin `adminer:5` (unpinned
  tahal starý PHP 7.4 image).
- E2E image `mcr.microsoft.com/playwright:vX.Y.Z` musí verzí odpovídat `@playwright/test`
  v `tests/e2e/package.json` (jinak se stahují browsery znovu / nesedí).
