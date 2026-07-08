<?php

declare(strict_types=1);

use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use Tester\Assert;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$db = TestDatabase::create();
$services = new ServiceRepository($db);
$payments = new PaymentRepository($db);

$serviceId = $services->insert([
	'name' => 'Nájem',
	'amount' => 1500000,
	'period' => 'monthly',
	'due_day' => 5,
]);
$otherServiceId = $services->insert([
	'name' => 'Elektřina',
	'amount' => 250000,
	'period' => 'monthly',
	'due_day' => 10,
]);

Assert::null($payments->find(1));
Assert::null($payments->findByServiceAndPeriod($serviceId, 2026, 1));

$janId = $payments->insert([
	'service_id' => $serviceId,
	'period_year' => 2026,
	'period_month' => 1,
	'due_date' => '2026-01-05',
	'amount' => 1500000,
]);
$febId = $payments->insert([
	'service_id' => $serviceId,
	'period_year' => 2026,
	'period_month' => 2,
	'due_date' => '2026-02-05',
	'paid_date' => '2026-02-03',
	'amount' => 1500000,
	'note' => 'Zaplaceno dřív',
]);
$otherJanId = $payments->insert([
	'service_id' => $otherServiceId,
	'period_year' => 2026,
	'period_month' => 1,
	'due_date' => '2026-01-10',
	'amount' => 250000,
]);

$jan = $payments->find($janId);
Assert::same($serviceId, $jan['service_id']);
Assert::null($jan['paid_date']);
// created_at si generuje repozitář sám — sjednoceno se ServiceRepository::insert().
Assert::truthy($jan['created_at']);

$found = $payments->findByServiceAndPeriod($serviceId, 2026, 2);
Assert::same($febId, $found['id']);
Assert::same('2026-02-03', $found['paid_date']);
Assert::same('Zaplaceno dřív', $found['note']);

$byService = $payments->findByService($serviceId);
Assert::count(2, $byService);
Assert::same($janId, $byService[0]['id']);
Assert::same($febId, $byService[1]['id']);

$byPeriod = $payments->findByPeriod(2026, 1);
Assert::count(2, $byPeriod);

$byYear = $payments->findByYear(2026);
Assert::count(3, $byYear);
Assert::same(1, $byYear[0]['period_month']);
Assert::same(1, $byYear[1]['period_month']);
Assert::same(2, $byYear[2]['period_month']);

// UNIQUE(service_id, period_year, period_month) -> chyba.
Assert::exception(
	static fn() => $payments->insert([
		'service_id' => $serviceId,
		'period_year' => 2026,
		'period_month' => 1,
		'due_date' => '2026-01-06',
		'amount' => 1000,
	]),
	PDOException::class,
);

$payments->update($janId, [
	'due_date' => '2026-01-05',
	'paid_date' => '2026-01-04',
	'amount' => 1500000,
	'note' => 'Zaplaceno včas',
]);
$updatedJan = $payments->find($janId);
Assert::same('2026-01-04', $updatedJan['paid_date']);
Assert::same('Zaplaceno včas', $updatedJan['note']);

$payments->delete($otherJanId);
Assert::null($payments->find($otherJanId));
