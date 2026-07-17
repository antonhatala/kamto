<?php

declare(strict_types=1);

use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Payment\PaymentService;
use Tester\Assert;
use Tests\Helpers\FakeClock;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$db = TestDatabase::create();
$services = new ServiceRepository($db);
$payments = new PaymentRepository($db);
$clock = new FakeClock(new DateTimeImmutable('2026-07-08'));
$paymentService = new PaymentService($payments, $services, $clock);

$monthlyId = $services->insert([
	'name' => 'Internet',
	'amount' => 60000,
	'period' => 'monthly',
	'due_day' => 12,
]);

$paymentService->markPaid($monthlyId, 2026, 7);
$row = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::notSame(null, $row);
Assert::same('2026-07-12', $row['due_date']);
Assert::same(60000, $row['amount']);
Assert::same('2026-07-08', $row['paid_date']);
Assert::null($row['skipped_at']);

$paymentService->markPaid($monthlyId, 2026, 7);
$sameRow = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::same($row['id'], $sameRow['id']);
Assert::count(1, $payments->findByService($monthlyId));

$paymentService->unmarkPaid($monthlyId, 2026, 7);
Assert::null($payments->findByServiceAndPeriod($monthlyId, 2026, 7)['paid_date']);

$paymentService->skip($monthlyId, 2026, 7);
$skipped = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::same('2026-07-08', $skipped['skipped_at']);
Assert::null($skipped['paid_date']);

$paymentService->unskip($monthlyId, 2026, 7);
Assert::null($payments->findByServiceAndPeriod($monthlyId, 2026, 7)['skipped_at']);

$paymentService->setAmount($monthlyId, 2026, 7, 65000);
$adjusted = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::same(65000, $adjusted['amount']);
Assert::same(60000, $services->find($monthlyId)['amount']);

$yearlyId = $services->insert([
	'name' => 'Doména',
	'amount' => 30000,
	'period' => 'yearly',
	'due_day' => 15,
	'due_month' => 3,
]);

$paymentService->markPaid($yearlyId, 2026, 3);
$yearlyRow = $payments->findByServiceAndPeriod($yearlyId, 2026, 3);
Assert::notSame(null, $yearlyRow);
Assert::same('2026-03-15', $yearlyRow['due_date']);

$otherYearlyId = $services->insert([
	'name' => 'Hosting',
	'amount' => 120000,
	'period' => 'yearly',
	'due_day' => 1,
	'due_month' => 9,
]);
$paymentService->markPaid($otherYearlyId, 2027, 5);
Assert::notSame(null, $payments->findByServiceAndPeriod($otherYearlyId, 2027, 9));
Assert::null($payments->findByServiceAndPeriod($otherYearlyId, 2027, 5));

Assert::exception(
	static fn() => $paymentService->markPaid(99999, 2026, 7),
	InvalidArgumentException::class,
);

$archivedId = $services->insert([
	'name' => 'Zrušené předplatné',
	'amount' => 19900,
	'period' => 'monthly',
	'due_day' => 3,
]);
$services->archive($archivedId);
Assert::exception(
	static fn() => $paymentService->markPaid($archivedId, 2026, 7),
	InvalidArgumentException::class,
);
Assert::exception(
	static fn() => $paymentService->skip($archivedId, 2026, 7),
	InvalidArgumentException::class,
);
Assert::exception(
	static fn() => $paymentService->setAmount($archivedId, 2026, 7, 100),
	InvalidArgumentException::class,
);
Assert::count(0, $payments->findByService($archivedId));

$exclId = $services->insert([
	'name' => 'Výlučnost',
	'amount' => 50000,
	'period' => 'monthly',
	'due_day' => 15,
]);

$paymentService->skip($exclId, 2026, 8);
$paymentService->markPaid($exclId, 2026, 8);
$afterPay = $payments->findByServiceAndPeriod($exclId, 2026, 8);
Assert::same('2026-07-08', $afterPay['paid_date']);
Assert::null($afterPay['skipped_at']);

$paymentService->skip($exclId, 2026, 8);
$afterSkip = $payments->findByServiceAndPeriod($exclId, 2026, 8);
Assert::same('2026-07-08', $afterSkip['skipped_at']);
Assert::null($afterSkip['paid_date']);

$paymentService->skip($exclId, 2026, 9);
$paymentService->markPaid($exclId, 2026, 9);
$paymentService->unmarkPaid($exclId, 2026, 9);
$afterUnpay = $payments->findByServiceAndPeriod($exclId, 2026, 9);
Assert::null($afterUnpay['paid_date']);
Assert::null($afterUnpay['skipped_at']);

$slidingId = $services->insert([
	'name' => 'Klouzavá platba',
	'amount' => 15000,
	'period' => 'monthly',
	'due_day' => 1,
	'is_sliding' => 1,
]);
$paymentService->markPaid($slidingId, 2027, 2);
$slidingRow = $payments->findByServiceAndPeriod($slidingId, 2027, 2);
Assert::same('2027-02-28', $slidingRow['due_date']);
