---
name: issue
description: Zpracuj zadané issue přes týmovou rotaci — izolovaná větev, plán rozpadlý na incrementy, každý increment projde rotací až po commit.
disable-model-invocation: true
---

Vede jedno **issue** od zadání po hotové commity: izoluje práci do větve, rozpadne ji na
**incrementy** a každý protáhne **rotací** týmu agentů až po `master` sign-off a commit. Kotva je
**předvídatelnost** — stejný proces pokaždé, ne práce na `main` a ne velké nerozdělené commity.

- **increment** = nejmenší samostatně review-ovatelný a zelený kus. **1 increment = 1 commit.**
- **rotace** = pevné pořadí rolí, kterým každý increment projde.

## 1. Příjem issue
Zachyť zadání (z argumentu skillu, jinak se doptej): **cíl**, **kontext**, **hotovo-když**
(akceptační náznak). Nejasnosti, které mění rozsah, vyřeš teď — ne během kódu.
✅ **Hotovo když:** cíl + hrubá akceptační kritéria jsou zapsané jednou–dvěma větami a žádná otevřená
nejasnost nemění rozsah.

## 2. Izolace do větve
Práce běží mimo `main`. Srovnej `main` a založ větev z něj:
```
git switch main && git pull --ff-only
git switch -c feature/<slug>
```
`<slug>` = krátký kebab-case z názvu issue. (Worktree místo větve → reference „Izolace".)
✅ **Hotovo když:** jsi na `feature/<slug>` a `git status` je čistý.

## 3. Plán po incrementech
Spusť agenta `product`: sepíše **akceptační kritéria** a rozpadne issue na **uspořádaný seznam
incrementů** — každý samostatně nasaditelný, review-ovatelný a zelený, s vlastním „hotovo-když".
Plán **ukaž uživateli ke schválení**; teprve pak se kóduje.
✅ **Hotovo když:** existuje uživatelem schválený uspořádaný seznam incrementů, každý s checkable
„hotovo-když".

## 4. Rotace na každý increment
Pro každý increment v pořadí. Napřed **naškáluj obřad** dle velikosti (reference „Škálování"), pak:
1. **Implementace** — příslušný dev: `backend-dev` (server/DB/migrace/auth), `frontend-dev`
   (Latte/Tailwind/JS/PWA), `devops` (Docker/CI/deploy).
2. **e2e** — `e2e` proklikne flow reálným prohlížečem, když increment mění user-facing chování.
3. **Kontroly paralelně (read-only)** — `security` ∥ `code-reviewer` ∥ `qa` na hotový increment.
4. **Oprava nálezů** — příslušný dev opraví; kontroly zopakuj, dokud jsou blokery pryč.
5. **Sign-off** — `master`: finální brána (kritéria + review + konvence + migrace). Verdikt ✅, nebo
   blokery zpět na krok 4.
6. **Commit** — až po `master ✅`: commit dělá orchestrátor. **1 commit = 1 schválený increment.
   Message česky. BEZ co-author traileru.**
✅ **Hotovo když:** každý increment z plánu má `master ✅` a vlastní commit; `git status` čistý.

## 5. Uzavření
Shrň hotové (seznam commitů). Nabídni **push + PR/MR**, ale proveď je **až na pokyn uživatele**.
✅ **Hotovo když:** uživatel má souhrn a další krok (push/PR) čeká na jeho pokyn.

## Reference: role & guardraily
- **Dev role (editují kód):** `backend-dev`, `frontend-dev`, `devops`. `e2e` píše jen do `tests/e2e/`.
- **Kontrolní role jsou read-only (needitují kód):** `product`, `security`, `code-reviewer`, `qa`,
  `master`.
- **Model tiering:** dev + `e2e` sonnet; kontrolní (`security`/`code-reviewer`/`qa`/`master`) opus/high.
- **Commit dělá jen orchestrátor** po `master ✅`; tajnosti nikdy do commitu (env / gitignored config).
- Detail rolí: `.claude/agents/*.md`; proces a konvence: `CLAUDE.md`.

## Reference: škálování obřadu
- **Drobnost** (typo, copy, mikro-fix): jen příslušný dev + rychlý `master` check. Bez plné rotace.
- **Feature / fáze:** plná rotace (`product` → dev(y) → `e2e` → `security ∥ code-reviewer ∥ qa` →
  `master`).
- **Akcelerátor:** tytéž role jde pustit i přes **Workflow** v jednom běhu
  (`product → [devs] → e2e → [security, code-reviewer, qa] → master`), když chceš paralelizovat.

## Reference: izolace — větev vs worktree
- **Větev (default):** stejná složka, `git switch -c`. Sedí na docker-compose bind-mount (dev/test
  loop beze změny). Naráz jedna vytažená větev.
- **Worktree:** samostatná složka `git worktree add ../kamto-<slug> -b feature/<slug>` s vlastní
  vytaženou větví. Použij, když chceš `main` držet běžící a netknutý paralelně, nebo pustit víc
  současně editujících agentů. Cena: běžící compose mountuje hlavní složku → worktree potřebuje
  vlastní stack. Po zmergování ukliď: `git worktree remove ../kamto-<slug>`.
