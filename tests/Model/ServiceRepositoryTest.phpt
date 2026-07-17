<?php

declare(strict_types=1);

use App\Model\ServiceRepository;
use Tester\Assert;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$repo = new ServiceRepository(TestDatabase::create());

Assert::same([], $repo->findAll());
Assert::null($repo->find(1));

$id = $repo->insert([
	'name' => 'Netflix',
	'amount' => 29900,
	'period' => 'monthly',
	'due_day' => 15,
]);
Assert::same(1, $id);

$service = $repo->find($id);
Assert::same('Netflix', $service['name']);
Assert::same(29900, $service['amount']);
Assert::same('monthly', $service['period']);
Assert::same(15, $service['due_day']);
Assert::null($service['due_month']);
Assert::null($service['category_id']);
Assert::same(0, $service['is_archived']);
Assert::null($service['archived_at']);
// is_sliding — default 0 (běžná služba), když insert() příznak vůbec nepošle.
Assert::same(0, $service['is_sliding']);
// created_at si generuje repozitář sám (dnešní datum, ISO 8601) — volající ho neposílá.
Assert::truthy($service['created_at']);
Assert::same(date('Y-m-d'), substr($service['created_at'], 0, 10));

$yearlyId = $repo->insert([
	'name' => 'Doména',
	'amount' => 50000,
	'period' => 'yearly',
	'due_day' => 1,
	'due_month' => 6,
	'is_sliding' => 1,
]);
$yearly = $repo->find($yearlyId);
Assert::same('yearly', $yearly['period']);
Assert::same(6, $yearly['due_month']);
// is_sliding — round-trip explicitně poslané hodnoty 1 (klouzavá služba).
Assert::same(1, $yearly['is_sliding']);

// findAll — automatické řazení is_sliding, due_day, id (viz CLAUDE.md): Netflix (due_day 15,
// neklouzavá) před Doménou (klouzavá -> vždy na konci).
$all = $repo->findAll();
Assert::count(2, $all);
Assert::same('Netflix', $all[0]['name']);
Assert::same('Doména', $all[1]['name']);

// update — plná náhrada šablony; is_sliding je zde POVINNÝ klíč (guardrail proti tichému
// resetu klouzavé služby při editaci, viz ServiceRepository::update()).
$repo->update($id, [
	'name' => 'Netflix Premium',
	'amount' => 39900,
	'period' => 'monthly',
	'due_day' => 20,
	'due_month' => null,
	'category_id' => null,
	'is_sliding' => 0,
]);
Assert::same('Netflix Premium', $repo->find($id)['name']);
Assert::same(39900, $repo->find($id)['amount']);
Assert::same(0, $repo->find($id)['is_sliding']);

// update — is_sliding round-trip explicitně poslané hodnoty 1.
$repo->update($id, [
	'name' => 'Netflix Premium',
	'amount' => 39900,
	'period' => 'monthly',
	'due_day' => 20,
	'due_month' => null,
	'category_id' => null,
	'is_sliding' => 1,
]);
Assert::same(1, $repo->find($id)['is_sliding']);

// archive -> is_archived=1 + archived_at nastavené, mizí z findAll() bez příznaku.
$repo->archive($id);
$archived = $repo->find($id);
Assert::same(1, $archived['is_archived']);
Assert::notSame(null, $archived['archived_at']);
Assert::count(1, $repo->findAll());
Assert::count(2, $repo->findAll(true));

// reactivate -> zpátky aktivní, archived_at zase NULL.
$repo->reactivate($id);
$reactivated = $repo->find($id);
Assert::same(0, $reactivated['is_archived']);
Assert::null($reactivated['archived_at']);
Assert::count(2, $repo->findAll());

$repo->delete($id);
Assert::null($repo->find($id));

// findArchived() — nový záznam archivujeme, ostatní zůstávají aktivní.
Assert::same([], $repo->findArchived());
$repo->archive($yearlyId);
$archivedList = $repo->findArchived();
Assert::count(1, $archivedList);
Assert::same($yearlyId, $archivedList[0]['id']);
$repo->reactivate($yearlyId);

// --- Automatické řazení (is_sliding ASC, due_day ASC, id ASC), viz CLAUDE.md. ---

$d20 = $repo->insert(['name' => 'D20', 'amount' => 100, 'period' => 'monthly', 'due_day' => 20]);
$d5 = $repo->insert(['name' => 'D5', 'amount' => 100, 'period' => 'monthly', 'due_day' => 5]);
$d1 = $repo->insert(['name' => 'D1', 'amount' => 100, 'period' => 'monthly', 'due_day' => 1]);
$slidingA = $repo->insert(['name' => 'SlidingA', 'amount' => 100, 'period' => 'monthly', 'due_day' => 1, 'is_sliding' => 1]);
$slidingB = $repo->insert(['name' => 'SlidingB', 'amount' => 100, 'period' => 'monthly', 'due_day' => 1, 'is_sliding' => 1]);
// Neklouzavá se stejným due_day jako $d1 (tie-break podle id — vloženo POZDĚJI než $d1, takže je za ním).
$d1b = $repo->insert(['name' => 'D1b', 'amount' => 100, 'period' => 'monthly', 'due_day' => 1]);

// Neklouzavé dle due_day (1,1,5,20), tie-break id u shodného due_day; klouzavé (yearlyId,
// slidingA, slidingB — všechny is_sliding=1) až na konci, mezi sebou tie-break id.
Assert::same(
	[$d1, $d1b, $d5, $d20, $yearlyId, $slidingA, $slidingB],
	array_column($repo->findAll(), 'id'),
);

// findArchived() musí mít stejné automatické řazení jako findAll() — archivujeme 3 služby
// napříč skupinami (neklouzavé s různým due_day + jednu klouzavou) a ověříme pořadí.
$repo->archive($d20);
$repo->archive($d5);
$repo->archive($slidingA);
Assert::same(
	[$d5, $d20, $slidingA],
	array_column($repo->findArchived(), 'id'),
);
