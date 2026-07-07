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
	'created_at' => '2026-01-01T00:00:00+01:00',
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

$yearlyId = $repo->insert([
	'name' => 'Doména',
	'amount' => 50000,
	'period' => 'yearly',
	'due_day' => 1,
	'due_month' => 6,
	'icon' => '🌐',
	'note' => 'Roční poplatek',
	'created_at' => '2026-01-01T00:00:00+01:00',
	'sort_order' => 5,
]);
$yearly = $repo->find($yearlyId);
Assert::same('yearly', $yearly['period']);
Assert::same(6, $yearly['due_month']);
Assert::same('🌐', $yearly['icon']);
Assert::same('Roční poplatek', $yearly['note']);
Assert::same(5, $yearly['sort_order']);

// findAll bez archivovaných, řazeno dle sort_order.
$all = $repo->findAll();
Assert::count(2, $all);
Assert::same('Netflix', $all[0]['name']);
Assert::same('Doména', $all[1]['name']);

// update — plná náhrada šablony.
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
	'created_at' => '2026-01-01T00:00:00+01:00',
	'sort_order' => 5,
]);
Assert::same(
	[$yearlyId, $tieId],
	array_column($repo->findAll(), 'id'),
);
