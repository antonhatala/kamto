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
- **Terminologie dle glosáře `CONTEXT.md`** (ubiquitous language, CZ ⇄ EN páry) — nové pojmy
  tam přidávej, kolize hlas.
- `declare(strict_types=1);` v každém PHP souboru. Odsazení **tabulátory**.
- Namespace `App\`, PSR-4 `App\` → `app/`. Bez Doctrine — raw SQL v repozitářích (styl `uzvimze`/`tomascinder`).
- **Peníze = integer haléře** (CZK×100); formátovat až při zobrazení.
- Datum/čas ISO 8601 v DB; časová zóna `Europe/Prague`; locale `cs`.
- **PHPStan** (co nejvyšší level) + **nette/tester** tam, kde to dává smysl.
- Komentáře česky; identifikátory anglicky.

## Design (světlý, minimalistický, BEZ dark mode)
- Řídit se **frontend-design pluginem**. Světlé pozadí, vzdušný layout, jemné stíny, zaoblené rohy.
- Barvy: neutrální základ + jedna akcentní barva; každá kategorie má vlastní barvu (pro heatmapu).
- Typografie: sans-serif, jasná hierarchie. Responsivní mobile-first (PWA).
- Přístupnost: kontrast, focus stavy, klávesnice. **Žádný dark mode.**
- Design tokeny (barvy/spacing/radius) udržuje `frontend-dev` v Tailwind konfiguraci / `src/css/app.css`
  a dokumentuje je zde.

### Design tokeny (`src/css/app.css`, Tailwind v4 `@theme`)
- **Neutrální základ:** Tailwind vestavěná škála `stone` (beze změny) — pozadí `stone-50`, text
  `stone-900`, tlumený text `stone-500`, okraje `stone-200`/`stone-300`.
- **Akcent — „copper/terracotta":** vlastní škála `accent-{50,100,200,300,500,600,700}`.
  `#fbf0e6 · #f6dfc9 · #eac29c · #dca277 · #c1622e · #a6501f · #7f3d18`. `accent-600`/`accent-700`
  mají ověřený kontrast ≥4.5:1 na bílém textu (tlačítka, odkazy); `accent-500` a světlejší jen na
  pozadí/okraje/ringy (ne bílý text na nich — kontrast pod AA).
- **Chyby:** vestavěná Tailwind `red-50`/`red-600/700` (flash, inline chyby, `.btn-danger`).
- **Radius:** vestavěná škála (`rounded-lg/xl/2xl`) na inputy/tlačítka/menší prvky + vlastní
  token `--radius-card: 1.25rem` → utilita `rounded-card` pro hlavní karty (login, formuláře).
- **Spacing:** vestavěná Tailwind v4 škála (`--spacing: 0.25rem` krok), beze změny.
- **Font:** vestavěný `font-sans` (systémový UI stack) — žádné webfonty (offline-friendly, bez
  závislosti na síti; důležité i pro pozdější PWA/offline).
- **Focus stavy:** `focus:ring-2 focus:ring-accent-200/300` + `focus:border-accent-500`.
- **Komponentní třídy** (`@layer components` v `src/css/app.css`, záměrně malá sada):
  `.input`, `.field-label`, `.field-error`, `.btn-primary`, `.btn-danger`, `.btn-ghost`,
  `.btn-icon`, `.segment-option` (segmentový radio přepínač) a `.yearly-only` (progresivní
  odhalení pole přes CSS `:has()`, bez JS). Heatmapa: `.heatmap-grid` (13 sloupců, řádky
  `display:contents`), `.heatmap-cell`, `.hm-box` (čtvercová dlaždice), `.hm-hatch` (šrafování).
- **Barvy kategorií:** serverový whitelist `CategoryPresenter::Palette` (8 tlumených odstínů
  ladících s terakotou). Pozor: dynamický hex ve `style` atributu vyžaduje `|noescape`
  (Latte escapuje `#` na `\#` → neplatné CSS); bezpečné jen pro hodnoty z tohoto whitelistu.
- **Stav = nikdy jen barva:** stav řádku (po splatnosti / zaplaceno / přeskočeno) je vždy
  i textový/ikonový, ne pouze barevný (přístupnost). „Po splatnosti" = `red-*` tokeny střídmě
  (tinted řádek `bg-red-50/60` + `border-red-200`), nulový zbytek k zaplacení = `emerald-600`.
- **Dialog úpravy částky:** nativní `<dialog>` + `showModal()` (Esc a focus-trap řeší prohlížeč).
  Minimální vanilla JS (delegovaný listener na `data-dialog-open`/`data-dialog-close`, žádná
  knihovna); po chybě validace se dialog znovu otevře přes `data-reopen`. Bez JS zůstává
  formulář odeslatelný (dialog je součást stránky).
- **Heatmapa (Fáze 4)** — ruční CSS grid, žádná JS knihovna. „Řeč buňky" (6 stavů) je jediný
  sdílený `{define heatmapCell}` v `app/Templates/_heatmap.latte`, importovaný jak velkou
  heatmapou (Overview), tak mini-heatmapou detailu služby — nezduplikovat. Stavy rozlišitelné
  i bez barvy: Paid=plná výplň barvou kategorie, Skipped=barva+šrafování+„–", Overdue=červený
  tint+„⚠" (boří barvu kategorie), Planned=čárkovaný okraj, Gap=prázdná buňka, Inactive=jemná
  neutrální. Každá buňka má `aria-label`/`title` „služba · měsíc rok · stav · částka". Pozor:
  `{import '../_heatmap.latte'}` musí být **uvnitř** `{block content}` (s layoutem se top-level
  kód šablony před renderem bloku nespustí → „undefined block"). Roční graf = inline SVG (no-JS,
  `viewBox`, `fill-accent-500`), per-měsíc součty počítány z plateb v `YearSummary` (Σ == headline).

## Datový model (shrnutí, detail v docs/PLAN.md)
`category`, `service` (opakující se šablona), `payment` (platba za konkrétní období), `_migration`.
Stavy odvozené: `paid_date`!=NULL → *zaplaceno*; NULL a `due_date`<dnes → *po splatnosti*; jinak
*naplánováno*. Historie/pauza = měsíce bez `payment` řádku (mezera v heatmapě).

## Tým agentů & proces
Projekt staví **virtuální tým** (`.claude/agents/`): `product`, `backend-dev`, `frontend-dev`,
`devops`, `e2e`, `security`, `code-reviewer`, `qa`, `master`. Hlavní session = **orchestrátor /
tech-lead** (sekvencuje, předává kontext, commituje po sign-offu).

**Rotace na každou fázi/feature:** `product` (akceptační kritéria) → `backend-dev` → `frontend-dev`
→ `devops` → `e2e` (Playwright v Dockeru — prokliká flow reálným prohlížečem) → **kontroly
paralelně:** `security` (bezpečnost BE/FE) ∥ `code-reviewer` (efektivita/minimalismus/konzistence)
∥ `qa` (testy + `/verify`) → opravy dle nálezů (příslušný dev) → `master` (sign-off) → **commit**.

- **Kontrolní role (`product`, `security`, `code-reviewer`, `qa`, `master`) jsou read-only** —
  nesmí editovat kód. Role `e2e` píše jen do `tests/e2e/` (Playwright testy + harness).
- **Škáluj obřad podle změny:** plná rotace na fázi; drobnost (typo/copy) jen BE/FE + rychlý master check.
- **Model tiering:** dev role + `e2e` sonnet, kontrolní (`security`, `code-reviewer`, `qa`,
  `master`) opus/high effort.
- **1 commit = 1 schválený increment** (po `master` ✓). **BEZ co-author traileru.** Commit dělá
  jen orchestrátor po `master` sign-offu.
- Akcelerátor: stejné role lze spustit i přes Workflow v jednom běhu
  (`product → [backend, frontend, devops] → e2e → [security, code-reviewer, qa] → master`).
- **Vendorované skilly** (`.claude/skills/`, viz tamní README): `code-review` (dvouosá recenze
  Standards × Spec — používá code-reviewer a master), `diagnosing-bugs` (dev role při opravách),
  `codebase-design` (terminologie návrhu), `domain-modeling` (glosář `CONTEXT.md`),
  `writing-great-skills` (autorství vlastních skillů, např. `dataviz` ve Fázi 4).

## Lokální vývoj
Na hostu není potřeba **žádný** nástroj (žádné PHP, Composer, Node/npm) — všechno běží přes
Docker / `docker compose`. Jediná závislost je Docker samotný.
```bash
docker compose up -d --build          # app: http://localhost:8080 · Adminer: http://localhost:8081
docker compose run --rm composer install     # PHP závislosti (poprvé / po změně composer.json)
docker compose run --rm node npm install     # JS závislosti (poprvé / po změně package.json)
docker compose run --rm node npm run css     # build Tailwind CSS (watch: `... npm run css:watch`)
docker compose run --rm php php bin/migrate.php   # aplikuje migrace (vytvoří var/kamto.db)
docker compose run --rm composer test        # PHPStan + nette/tester
docker compose down                   # zastavit a uklidit
```
`composer`/`node` jsou one-shot tooling služby (profil `tools`, mimo `docker compose up`) —
`docker compose run --rm <služba> <příkaz>` je spustí, provede příkaz a kontejner smaže.

**Adminer** (http://localhost:8081): System **SQLite**, Username prázdné, heslo **`kamto`**
(vstupní heslo Admineru, plugin login-password-less), Database **`/data/kamto.db`**.

## Fáze (viz docs/PLAN.md)
0 kostra+login · 1 DB+migrace+schéma · 2 CRUD služeb · 3 platby · 4 přehledy/heatmapa · 5 PWA+UX · 6 deploy Bunny.
