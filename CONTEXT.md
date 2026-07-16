# Kamto — evidence pravidelných plateb

Doménový glosář (ubiquitous language) projektu Kamto. Kód používá anglické identifikátory,
UI a dokumentace češtinu — tabulka fixuje kanonické páry. Udržuje skill `domain-modeling`.

## Language

**Služba** (`service`):
Opakující se platební závazek — šablona s částkou, periodou a splatností (Netflix, nájem…).
Není to platba samotná.
_Avoid_: předplatné, subscription, položka, item

**Platba** (`payment`):
Záznam o (očekávaném či provedeném) zaplacení služby za jedno konkrétní období.
Max. jedna na službu a období.
_Avoid_: transakce, úhrada (v kódu), record

**Kategorie** (`category`):
Pojmenovaná barevná skupina služeb (Zábava, Bydlení…); barva se propisuje do heatmapy.
_Avoid_: štítek, tag, skupina

**Období** (`period_year` + `period_month`):
Kalendářní měsíc, ke kterému se platba vztahuje. I roční služba má období (svůj měsíc splatnosti).
_Avoid_: interval, cyklus

**Perioda** (`period`):
Frekvence opakování služby: `monthly` (měsíční) nebo `yearly` (roční). Nic jiného neexistuje.
_Avoid_: frekvence, opakování, cyklus

**Splatnost** (`due_day`, `due_month`, `due_date`):
Orientační den (u roční i měsíc), kdy má být služba zaplacena. U kratších měsíců se den
29–31 posouvá na poslední den měsíce.
_Avoid_: deadline, termín

**Stav platby** (odvozený, není sloupec):
Žebříček (od nejvyšší priority): *zaplaceno* (`paid_date` není NULL) · *přeskočeno*
(`paid_date` NULL a `skipped_at` není NULL) · *po splatnosti* (obě NULL a `due_date` < dnes,
striktně — v den splatnosti je platba ještě naplánovaná) · *naplánováno* (jinak). Ukládá se
jen `paid_date`/`skipped_at`; stav se vždy počítá. Klouzavá služba nikdy nedá *po splatnosti*
(viz „Klouzavá služba" níže) — bez paid/skipped je vždy *naplánováno*.
_Avoid_: status sloupec, flag

**Klouzavá služba** (`service.is_sliding`):
Služba bez pevného dne splatnosti (platí se, „jak to vyjde") — jednorázová dohoda/nepravidelný
výdaj, ne měsíc co měsíc stejný den. Nikdy se pro ni neodvodí stav *po splatnosti*
(`PaymentStatus::derive`), i když je `due_date` v minulosti — chybějící/nezaplacená platba
zůstává *naplánováno*. `due_date` se pro ni dopočítává na poslední den měsíce (jen řazení „na
konec", k odvození stavu se nepoužívá). Výchozí hodnota `0` (běžná služba, beze změny chování).
_Avoid_: pohyblivá služba, flexibilní splatnost

**Přeskočeno** (`skipped_at`):
Reverzibilní pauza jedné platby za dané období — záměrně se neplatí, ale záznam zůstává
(na rozdíl od „žádný řádek", což znamená ještě neřešeno/budoucí období). `paid_date` je
NULL a `skipped_at` NOT NULL. Zrušitelné („Zrušit přeskočení") zpět na naplánováno/po
splatnosti podle `due_date`.
_Avoid_: zrušeno, smazáno, ignorováno

**Archivace** (`is_archived`, `archived_at`):
Reverzibilní ukrytí služby z aktivního seznamu; historie plateb zůstává. Opak = reaktivace
(„Obnovit"). Tvrdé mazání služeb v UI neexistuje.
_Avoid_: smazání, deaktivace, skrytí

**Pauza / mezera**:
Měsíc(e), kdy služba existovala, ale nemá řádek platby — v heatmapě prázdné buňky.
Záměrný signál, ne chyba dat.
_Avoid_: výpadek, díra

**Haléře** (`amount`):
Všechny částky v DB i kódu jsou integer haléře (CZK × 100). Na CZK se formátuje až
při zobrazení (`Money`/filter `|czk`).
_Avoid_: float koruny, desetinná čísla
