# Kamto

Minimalistická webová aplikace pro evidenci pravidelných plateb (nájem, internet, telefon,
Netflix, Claude…). Single-user, jen CZK, světlý minimalistický vzhled. Cíl: přehled „co tento
měsíc zaplatit" + historie/průběh plateb (heatmapa s mezerami).

## Stack
PHP 8.5 · Nette + Latte 3 · SQLite přes libSQL (Bunny Database) · Tailwind v4 · PWA.
Hosting: Bunny.net (Magic Containers + Bunny Database + CDN).

Detaily: [`docs/PLAN.md`](docs/PLAN.md) a [`CLAUDE.md`](CLAUDE.md).

## Vývoj (lokálně)
```bash
docker compose up            # app: http://localhost:8080 · Adminer: http://localhost:8081
npm install && npm run css   # build Tailwind CSS
php bin/migrate.php           # migrace
composer test                # PHPStan + testy
```

## Tým / proces
Projekt staví virtuální tým agentů (`.claude/agents/`): `product`, `backend-dev`, `frontend-dev`,
`devops`, `qa`, `master`. Rotace na fázi: product → backend → frontend → devops → qa → master →
commit. Viz [`CLAUDE.md`](CLAUDE.md).

## Fáze
0 kostra+login · 1 DB+migrace+schéma · 2 CRUD služeb · 3 platby · 4 přehledy/heatmapa · 5 PWA+UX · 6 deploy Bunny.
