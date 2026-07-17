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
Assert::truthy($jan['created_at']);

$found = $payments->findByServiceAndPeriod($serviceId, 2026, 2);
Assert::same($febId, $found['id']);
Assert::same('2026-02-03', $found['paid_date']);

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
]);
$updatedJan = $payments->find($janId);
Assert::same('2026-01-04', $updatedJan['paid_date']);

$payments->delete($otherJanId);
Assert::null($payments->find($otherJanId));

$marId = $payments->insert([
	'service_id' => $serviceId,
	'period_year' => 2026,
	'period_month' => 3,
	'due_date' => '2026-03-05',
	'amount' => 1500000,
	'skipped_at' => '2026-03-01',
]);
$mar = $payments->find($marId);
Assert::same('2026-03-01', $mar['skipped_at']);
Assert::null($mar['paid_date']);

$payments->setPaidDate($marId, '2026-03-02');
Assert::same('2026-03-02', $payments->find($marId)['paid_date']);
$payments->setPaidDate($marId, null);
Assert::null($payments->find($marId)['paid_date']);

$payments->setSkipped($marId, null);
Assert::null($payments->find($marId)['skipped_at']);
$payments->setSkipped($marId, '2026-03-03');
Assert::same('2026-03-03', $payments->find($marId)['skipped_at']);

$payments->setAmount($marId, 1600000);
$updatedMar = $payments->find($marId);
Assert::same(1600000, $updatedMar['amount']);
Assert::same('2026-03-03', $updatedMar['skipped_at']);
Assert::same('2026-03-05', $updatedMar['due_date']);

$payments->insertIgnore([
	'service_id' => $serviceId,
	'period_year' => 2026,
	'period_month' => 3,
	'due_date' => '2026-03-31',
	'amount' => 9999999,
	'paid_date' => '2026-03-31',
]);
$stillMar = $payments->find($marId);
Assert::same(1600000, $stillMar['amount']);
Assert::same('2026-03-05', $stillMar['due_date']);
Assert::null($stillMar['paid_date']);
Assert::count(1, array_filter(
	$payments->findByService($serviceId),
	static fn(array $p): bool => $p['period_year'] === 2026 && $p['period_month'] === 3,
));

$payments->insertIgnore([
	'service_id' => $serviceId,
	'period_year' => 2026,
	'period_month' => 4,
	'due_date' => '2026-04-05',
	'amount' => 1500000,
]);
Assert::notSame(null, $payments->findByServiceAndPeriod($serviceId, 2026, 4));
