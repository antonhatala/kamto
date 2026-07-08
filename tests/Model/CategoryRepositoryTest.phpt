<?php

declare(strict_types=1);

use App\Model\CategoryRepository;
use App\Model\ServiceRepository;
use Tester\Assert;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$db = TestDatabase::create();
$repo = new CategoryRepository($db);

Assert::same([], $repo->findAll());
Assert::null($repo->find(1));

$id1 = $repo->insert(['name' => 'Bydlení', 'color' => '#c1622e', 'sort_order' => 2]);
$id2 = $repo->insert(['name' => 'Zábava', 'color' => '#eac29c', 'sort_order' => 1]);
Assert::same(1, $id1);
Assert::same(2, $id2);

// findAll řazeno podle sort_order.
$all = $repo->findAll();
Assert::count(2, $all);
Assert::same('Zábava', $all[0]['name']);
Assert::same('Bydlení', $all[1]['name']);

// insert bez sort_order -> default 0.
$id3 = $repo->insert(['name' => 'Bez pořadí', 'color' => '#000']);
Assert::same(0, $repo->find($id3)['sort_order']);

$found = $repo->find($id1);
Assert::same('Bydlení', $found['name']);
Assert::same('#c1622e', $found['color']);

// injection-safe: hodnota s uvozovkou i pokusem o SQL injection se uloží doslovně.
$id4 = $repo->insert(['name' => "O'Brien", 'color' => '#000', 'sort_order' => 0]);
Assert::same("O'Brien", $repo->find($id4)['name']);

$id5 = $repo->insert(['name' => "'); DROP TABLE service;--", 'color' => '#000']);
Assert::same("'); DROP TABLE service;--", $repo->find($id5)['name']);

// Deterministické řazení: shodný sort_order (id3–id5 mají 0) -> tie-break podle id.
Assert::same(
	[$id3, $id4, $id5, $id2, $id1],
	array_column($repo->findAll(), 'id'),
);

$repo->update($id1, ['name' => 'Bydlení a energie', 'color' => '#a6501f', 'sort_order' => 3]);
$updated = $repo->find($id1);
Assert::same('Bydlení a energie', $updated['name']);
Assert::same('#a6501f', $updated['color']);
Assert::same(3, $updated['sort_order']);

// countServices() — 0, dokud na kategorii nic neukazuje; jinak počet služeb s daným category_id.
$services = new ServiceRepository($db);
Assert::same(0, $repo->countServices($id1));

$services->insert(['name' => 'Nájem', 'amount' => 1000000, 'period' => 'monthly', 'due_day' => 1, 'category_id' => $id1]);
$services->insert(['name' => 'Elektřina', 'amount' => 250000, 'period' => 'monthly', 'due_day' => 15, 'category_id' => $id1]);
$services->insert(['name' => 'Netflix', 'amount' => 29900, 'period' => 'monthly', 'due_day' => 5, 'category_id' => $id2]);
Assert::same(2, $repo->countServices($id1));
Assert::same(1, $repo->countServices($id2));

// nextSortOrder() — o jedno za nejvyšším sort_order (id1 má 3 po update výše).
Assert::same(4, $repo->nextSortOrder());

$repo->delete($id1);
Assert::null($repo->find($id1));
