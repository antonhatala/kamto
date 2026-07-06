# Kamto

Minimalistická webová aplikace pro evidenci pravidelných plateb (nájem, internet, telefon,
Netflix, Claude…). **Single-user, jen CZK, světlý minimalistický vzhled (bez dark mode).**
Cíl: rychlý přehled „co tento měsíc zaplatit" + čitelná **historie/průběh** plateb (které měsíce
jsem co platil, kde jsou pauzy → heatmapa s mezerami).

> Detailní plán a datový model: `docs/PLAN.md`.

## Stack
- **PHP 8.5** · **Nette** (application, di, http, security, forms, robot-loader) + **Latte 3.1** + Tracy.
- **Data:** SQLite přes tenkou bránu `App\Database\Db`. Primárně nativní **libSQL PHP extension**
  (`tursodatabase/turso-client-php`) — `file:var/kamto.db` lokálně, vzdálené libSQL URL na Bunny.
  Fallback `PdoSqliteDb`. Repozitáře = **raw SQL** (žádné Doctrine/ORM).
- **Migrace:** číslované `migrations/NNN_*.sql`, spouští `bin/migrate.php`, stav v tabulce `_migration`.
- **Frontend:** Latte + **Tailwind v4** (`@tailwindcss/cli`: `src/css/app.css` → `www/css/app.css`).
  Minimum JS: nativní `<dialog>`/Popover API + Nette snippets/Naja. Bez SPA frameworku.
- **PWA:** `manifest.json` + service worker.
- **Config:** NEON (`config/config.neon` + gitignored `config/config.local.neon`); na produkci env.
- **Hosting:** Bunny.net — Magic Containers (stateless) + Bunny Database (vzdálená libSQL) + CDN.

## Struktura
```
app/        Bootstrap.php, Presenters/, Model/, Database/, Templates/
www/        docroot: index.php, css/, js/, manifest.json, sw.js
config/     config.neon (+ gitignored config.local.neon)
migrations/ NNN_*.sql
bin/        migrate.php, console.php
src/css/    app.css (zdroj pro Tailwind)
var/        lokální SQLite (gitignored)
docs/       PLAN.md
tests/      nette/tester
```

## Konvence
- `declare(strict_types=1);` v každém PHP souboru. Odsazení **tabulátory**.
- Namespace `App\`, PSR-4 `App\` → `app/`. Bez Doctrine — raw SQL v repozitářích (styl `uzvimze`/`tomascinder`).
- **Peníze = integer haléře** (CZK×100); formátovat až při zobrazení.
- Datum/čas ISO 8601 v DB; časová zóna `Europe/Prague`; locale `cs`.
- **PHPStan** (co nejvyšší level) + **nette/tester** tam, kde to dává smysl.

## Design (světlý, minimalistický, BEZ dark mode)
- Řídit se **frontend-design pluginem**. Světlé pozadí, vzdušný layout, jemné stíny, zaoblené rohy.
- Barvy: neutrální základ + jedna akcentní barva; každá kategorie má vlastní barvu (pro heatmapu).
- Typografie: sans-serif, jasná hierarchie. Responsivní mobile-first (PWA).
- Přístupnost: kontrast, focus stavy, klávesnice. **Žádný dark mode.**
- Design tokeny (barvy/spacing/radius) udržuje `frontend-dev` v Tailwind konfiguraci / `src/css/app.css`
  a dokumentuje je zde.

## Datový model (shrnutí, detail v docs/PLAN.md)
`category`, `service` (opakující se šablona), `payment` (platba za konkrétní období), `_migration`.
Stavy odvozené: `paid_date`!=NULL → *zaplaceno*; NULL a `due_date`<dnes → *po splatnosti*; jinak
*naplánováno*. Historie/pauza = měsíce bez `payment` řádku (mezera v heatmapě).

## Tým agentů & proces
Projekt staví **virtuální tým** (`.claude/agents/`): `product`, `backend-dev`, `frontend-dev`,
`devops`, `qa`, `master`. Hlavní session = **orchestrátor / tech-lead** (sekvencuje, předává
kontext, commituje po sign-offu).

**Rotace na každou fázi/feature:** `product` (akceptační kritéria) → `backend-dev` → `frontend-dev`
→ `devops` → `qa` (testy + `/verify`) → `master` (sign-off) → **commit**.

- **Kontrolní role (`product`, `qa`, `master`) jsou read-only** — nesmí editovat kód.
- **Škáluj obřad podle změny:** plná rotace na fázi; drobnost (typo/copy) jen BE/FE + rychlý master check.
- **Model tiering:** dev role sonnet, kontrolní (`qa`, `master`) opus/high effort.
- **1 commit = 1 schválený increment** (po `master` ✓). **BEZ co-author traileru.** Commit dělá
  jen orchestrátor po `master` sign-offu.
- Akcelerátor: stejných 6 rolí lze spustit i přes Workflow v jednom běhu
  (`product → [backend, frontend, devops] → qa → master`).

## Lokální vývoj
```bash
docker compose up            # app: http://localhost:8080 · Adminer: http://localhost:8081
npm install && npm run css   # build Tailwind CSS (watch: npm run css:watch)
php bin/migrate.php           # aplikuje migrace
composer test                # PHPStan + nette/tester
```

## Fáze (viz docs/PLAN.md)
0 kostra+login · 1 DB+migrace+schéma · 2 CRUD služeb · 3 platby · 4 přehledy/heatmapa · 5 PWA+UX · 6 deploy Bunny.
