# Kamto — evidence pravidelných plateb

## Context

Minimalistická aplikace na evidenci pravidelných plateb (nájem, internet, telefon, Netflix,
Claude…). U každé položky se naplánuje orientační období splatnosti a potvrdí zaplacení. Platby
jsou **měsíční** i **roční**. Položky jde **archivovat a znovu aktivovat**. Klíčová hodnota je
**historie / průběh** (HBO led–bře, pauza, čer–čec) → hezké přehledy. Existující appky
(Wallet/Monefy/Spendee/Excel) nevyhovují — placené nebo málo minimalistické.

**Název:** Kamto („Kam to zase ty peníze mizí?").
**Repo:** `/Users/antonhatala/Projects/anton.hatala/kamto`, remote `git@gitlab:anton.hatala/kamto.git`.

**Rozhodnutí:** SQLite · hosting **Bunny.net** (all-in-one 2026) · **single-user + heslo** ·
**jen CZK**. Stack drží osobní styl z `tomascinder`/`uzvimze` (Nette + Latte + prosté SQL migrace
+ Tailwind v4 CLI), ne firemní `apploud/web-skeleton`.

**Bunny (ověřeno):** Bunny Database = *vzdálená* SQLite-kompatibilní služba (libSQL přes
HTTP/extension). Magic Containers jsou *stateless* → stav žije v Bunny DB. Data access proto jde
přes tenkou DB bránu s libSQL → appka zůstává přenositelná na jakýkoli VPS s lokálním SQLite
souborem beze změny logiky (pojistka).

## Multi-agent tým & proces (rotace)
Tým 9 rolí jako Claude Code subagenti v `.claude/agents/*.md`. Hlavní session = orchestrátor.
Rotace na fázi: **product** (kritéria) → **backend-dev** → **frontend-dev** → **devops** →
**e2e** (Playwright v Dockeru, prokliká flow) → kontroly paralelně: **security** (bezpečnost
BE/FE) ∥ **code-reviewer** (efektivita/minimalismus/sjednocení) ∥ **qa** (testy+`/verify`) →
opravy dle nálezů → **master** (sign-off) → **commit**.
- Kontrolní role (product, security, code-reviewer, qa, master) read-only; e2e píše jen do
  `tests/e2e/`. Škáluj obřad podle změny. Model tiering (dev+e2e sonnet, kontrola opus).
  1 commit = 1 increment po master ✓, bez co-author traileru.

## Tech stack (2026-07)
- PHP 8.5 · Docker `php:8.5-fpm` + nginx.
- Nette (application, di, http, security, forms, robot-loader) + Latte 3.1 + Tracy. Bez Doctrine.
- Data: brána `App\Database\Db` + `LibsqlDb` (nativní libSQL ext, `file:` lokál / vzdálené na Bunny)
  + fallback `PdoSqliteDb`. Repozitáře raw SQL.
- Migrace: `migrations/NNN_*.sql` + `bin/migrate.php`, stav v `_migration`.
- Frontend: Tailwind v4 (`@tailwindcss/cli`), minimum JS (nativní `<dialog>`/Popover + Naja).
  Vzhled světlý, minimalistický, **bez dark mode** (frontend-design plugin, bez screenshotů).
- Grafy: ruční CSS-grid heatmapa + SVG (skill `dataviz`). PWA: manifest + service worker.
- Auth: single-user, Nette Security, hash hesla z config/env; bez user tabulky.
- Peníze: integer haléře. Locale `cs`, `Europe/Prague`. PHPStan + nette/tester. GitLab CI.

## Datový model (SQLite)
- **category**: id, name, color (hex), sort_order
- **service** (šablona): id, name, amount (haléře), period (monthly|yearly), due_day (1–31),
  due_month (1–12, jen yearly), category_id (FK, null), icon (emoji), note, is_archived (0/1),
  created_at, archived_at, sort_order
- **payment** (za období): id, service_id (FK, CASCADE), period_year, period_month (1–12),
  due_date (ISO, plán), paid_date (ISO, NULL=nezaplaceno), amount (haléře), note, created_at ·
  UNIQUE(service_id, period_year, period_month)
- **_migration**: evidence migrací

Stavy: paid_date!=NULL → zaplaceno; NULL a due_date<dnes → po splatnosti; jinak naplánováno.
Historie/pauza = měsíce bez payment řádku (mezera v heatmapě).

## Fáze implementace
Vše do `kamto/`. Každá fáze projde rotací týmu, po ní `/verify` + `/code-review`, pak commit.

- **Krok 0 — Materializace do repa:** docs/PLAN.md, CLAUDE.md, .claude/agents/*, .gitignore,
  README.md → první commit. ✅ (probíhá)
- **Fáze 0 — Kostra & login:** Dockerfile + docker-compose (php+nginx+adminer), Nette bootstrap,
  Tailwind v4 pipeline, SignPresenter + single-user login.
- **Fáze 1 — DB brána, migrace, schéma:** Db + LibsqlDb (+PdoSqliteDb), bin/migrate.php,
  001_init.sql, repozitáře Service/Payment/Category.
- **Fáze 2 — CRUD služeb:** seznam, přidání/editace, archivace + reaktivace, řazení, kategorie.
- **Fáze 3 — Platby:** měsíční/roční splatnost, dashboard „Co zaplatit tento měsíc", Zaplaceno ✓,
  úprava částky, přeskočit.
- **Fáze 4 — Přehledy:** souhrn, heatmapa rok × služby (mezery), roční přehled po měsících (SVG)
  a kategoriích, detail služby.
- **Fáze 5 — PWA & UX:** manifest + sw.js, ikony, CSV export, doladění.
- **Fáze 6 — Deploy na Bunny:** image → GitLab registry → Magic Containers (1 instance; env
  DATABASE_URL/TOKEN/APP_PASSWORD_HASH), Bunny Database + migrace, CDN pull zone, .gitlab-ci.yml.

## Přípravné kroky (mimo kód)
1. **Design:** světlý, minimalistický (bez dark mode) — frontend-design plugin, bez screenshotů;
   tokeny nadefinuje frontend-dev do CLAUDE.md.
2. **Doména:** řeší uživatel zvlášť; do té doby random Bunny URL.
3. **Bunny:** účet + connection URL/token dodá uživatel, až si devops řekne.

## Verifikace
- Lokálně `docker compose up`, end-to-end: přidat službu → „tento měsíc" → potvrdit → heatmapa
  (mezera) → archiv/reaktivace → CSV. Měsíční i roční perioda. `bin/migrate.php` lokál i Bunny.
- Po Fázi 6: smoke test URL + ověřit, že restart kontejneru neztratí data (žijí v Bunny DB).

## Rizika / pojistky
- libSQL v PHP (2026 zralé) vyžaduje `.so` v image → řešíme v Dockerfile; fallback pure-PHP HTTP
  klient nebo VPS + lokální SQLite (`PdoSqliteDb` beze změny logiky).
- Stateless kontejnery + session: 1 instance; restart = nový login (u osobní appky OK).
