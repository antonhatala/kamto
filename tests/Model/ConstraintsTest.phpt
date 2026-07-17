<?php

declare(strict_types=1);

use App\Model\CategoryRepository;
use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use Tester\Assert;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$db = TestDatabase::create();
$categories = new CategoryRepository($db);
$services = new ServiceRepository($db);
$payments = new PaymentRepository($db);

$categoryId = $categories->insert(['name' => 'Domácnost', 'color' => '#c1622e']);
$serviceId = $services->insert([
	'name' => 'Internet',
	'amount' => 60000,
	'period' => 'monthly',
	'due_day' => 12,
	'category_id' => $categoryId,
]);
$paymentId = $payments->insert([
	'service_id' => $serviceId,
	'period_year' => 2026,
	'period_month' => 1,
	'due_date' => '2026-01-12',
	'amount' => 60000,
]);

Assert::notSame(null, $payments->find($paymentId));
$services->delete($serviceId);
Assert::null($payments->find($paymentId));

$categoryId2 = $categories->insert(['name' => 'Zábava', 'color' => '#eac29c']);
$serviceId2 = $services->insert([
	'name' => 'Spotify',
	'amount' => 17900,
	'period' => 'monthly',
	'due_day' => 1,
	'category_id' => $categoryId2,
]);
Assert::same($categoryId2, $services->find($serviceId2)['category_id']);

$categories->delete($categoryId2);
Assert::notSame(null, $services->find($serviceId2));
Assert::null($services->find($serviceId2)['category_id']);

Assert::exception(
	static fn() => $services->insert([
		'name' => 'Neplatná perioda',
		'amount' => 100,
		'period' => 'xxx',
		'due_day' => 1,
	]),
	PDOException::class,
);

Assert::exception(
	static fn() => $services->insert([
		'name' => 'Neplatný due_day',
		'amount' => 100,
		'period' => 'monthly',
		'due_day' => 0,
	]),
	PDOException::class,
);

Assert::exception(
	static fn() => $payments->insert([
		'service_id' => $serviceId2,
		'period_year' => 2026,
		'period_month' => 13,
		'due_date' => '2026-12-01',
		'amount' => 100,
	]),
	PDOException::class,
);
