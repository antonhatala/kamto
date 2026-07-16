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
	'icon' => '🌐',
	'note' => 'Roční poplatek',
	'sort_order' => 5,
	'is_sliding' => 1,
]);
$yearly = $repo->find($yearlyId);
Assert::same('yearly', $yearly['period']);
Assert::same(6, $yearly['due_month']);
Assert::same('🌐', $yearly['icon']);
Assert::same('Roční poplatek', $yearly['note']);
Assert::same(5, $yearly['sort_order']);
// is_sliding — round-trip explicitně poslané hodnoty 1 (klouzavá služba).
Assert::same(1, $yearly['is_sliding']);

// findAll bez archivovaných, řazeno dle sort_order.
$all = $repo->findAll();
Assert::count(2, $all);
Assert::same('Netflix', $all[0]['name']);
Assert::same('Doména', $all[1]['name']);

// update — plná náhrada šablony (bez is_sliding v $data -> defaultně se uloží 0).
$repo->update($id, [
	'name' => 'Netflix Premium',
	'amount' => 39900,
	'period' => 'monthly',
	'due_day' => 20,
	'due_month' => null,
	'category_id' => null,
	'icon' => null,
	'note' => null,
	'sort_order' => 1,
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
	'icon' => null,
	'note' => null,
	'sort_order' => 1,
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

// Deterministické řazení: shodný sort_order -> tie-break podle id.
$tieId = $repo->insert([
	'name' => 'Stejné pořadí jako Doména',
	'amount' => 100,
	'period' => 'monthly',
	'due_day' => 1,
	'sort_order' => 5,
]);
Assert::same(
	[$yearlyId, $tieId],
	array_column($repo->findAll(), 'id'),
);

// findArchived() — nový záznam archivujeme, ostatní zůstávají aktivní.
Assert::same([], $repo->findArchived());
$repo->archive($yearlyId);
$archivedList = $repo->findArchived();
Assert::count(1, $archivedList);
Assert::same($yearlyId, $archivedList[0]['id']);
$repo->reactivate($yearlyId);

// nextSortOrder() — o jedno za nejvyšším napříč všemi (vč. archivu). yearlyId i tieId mají 5.
Assert::same(6, $repo->nextSortOrder());

// Nový záznam s distinktním pořadím dá čitelný swap. nextSortOrder() ho odvodí (6).
$swapId = $repo->insert([
	'name' => 'Řazení',
	'amount' => 100,
	'period' => 'monthly',
	'due_day' => 1,
	'sort_order' => $repo->nextSortOrder(),
]);
Assert::same(6, (int) $repo->find($swapId)['sort_order']);
$repo->archive($swapId); // Archivace nesnižuje maximum — pořadí zůstává vyhrazené.
Assert::same(7, $repo->nextSortOrder());
$repo->reactivate($swapId);

// swapSortOrder() — atomicky prohodí pořadí dvou služeb (řazení se sousedem v presenteru).
$repo->swapSortOrder($yearlyId, $swapId); // yearly=5, swap=6
Assert::same(6, (int) $repo->find($yearlyId)['sort_order']);
Assert::same(5, (int) $repo->find($swapId)['sort_order']);
