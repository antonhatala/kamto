<?php

declare(strict_types=1);

use App\Payment\DashboardItem;
use App\Payment\MonthlyOverview;
use App\Payment\PaymentStatus;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-15');

/** @return array<string, mixed> */
function service(
	int $id,
	string $period,
	int $dueDay,
	int $amount,
	?int $dueMonth = null,
	int $isSliding = 0,
): array {
	return [
		'id' => $id,
		'name' => "Služba {$id}",
		'period' => $period,
		'due_day' => $dueDay,
		'due_month' => $dueMonth,
		'amount' => $amount,
		'is_sliding' => $isSliding,
	];
}

/** @return array<string, mixed> */
function payment(int $serviceId, string $dueDate, int $amount, ?string $paidDate, ?string $skippedAt): array
{
	return [
		'service_id' => $serviceId,
		'period_year' => 2026,
		'period_month' => 7,
		'due_date' => $dueDate,
		'paid_date' => $paidDate,
		'skipped_at' => $skippedAt,
		'amount' => $amount,
	];
}

$services = [
	service(1, 'monthly', 5, 10000),
	service(2, 'monthly', 20, 20000),
	service(3, 'monthly', 10, 30000),
	service(4, 'monthly', 25, 40000),
	service(5, 'yearly', 1, 50000, dueMonth: 7),
	service(6, 'yearly', 3, 60000, dueMonth: 3),
	service(7, 'monthly', 8, 70000),
];

$payments = [
	payment(3, '2026-07-10', 30000, '2026-07-09', null),
	payment(4, '2026-07-25', 40000, null, '2026-07-02'),
	payment(7, '2026-07-08', 70000, '2026-07-06', '2026-07-05'),
];

$result = MonthlyOverview::build(2026, 7, $today, $services, $payments);

Assert::same(['5', '1'], array_map(
	static fn(DashboardItem $i): string => (string) $i->service['id'],
	$result->sections['overdue'],
));
Assert::same(PaymentStatus::Overdue, $result->sections['overdue'][0]->status);

Assert::count(1, $result->sections['planned']);
Assert::same(2, $result->sections['planned'][0]->service['id']);
Assert::same(PaymentStatus::Planned, $result->sections['planned'][0]->status);

Assert::same([7, 3], array_map(
	static fn(DashboardItem $i): int => (int) $i->service['id'],
	$result->sections['paid'],
));

Assert::count(1, $result->sections['skipped']);
Assert::same(4, $result->sections['skipped'][0]->service['id']);

$allIds = [];
foreach ($result->sections as $section) {
	foreach ($section as $item) {
		$allIds[] = (int) $item->service['id'];
	}
}
Assert::false(in_array(6, $allIds, true));
Assert::count(6, $allIds);

$planned = $result->sections['planned'][0];
Assert::same('2026-07-20', $planned->dueDate);
Assert::same(20000, $planned->amount);

$paidThree = $result->sections['paid'][1];
Assert::same(3, $paidThree->service['id']);
Assert::same(30000, $paidThree->amount);
Assert::same('2026-07-10', $paidThree->dueDate);

Assert::same(80000, $result->remainingTotal);
Assert::same(100000, $result->paidTotal);

$empty = MonthlyOverview::build(2026, 7, $today, [], []);
Assert::same([], $empty->sections['overdue']);
Assert::same(0, $empty->remainingTotal);
Assert::same(0, $empty->paidTotal);

$slidingServices = [
	service(10, 'monthly', 5, 10000, isSliding: 1),
	service(11, 'monthly', 5, 10000, isSliding: 0),
];
$slidingResult = MonthlyOverview::build(2026, 5, $today, $slidingServices, []);

Assert::count(1, $slidingResult->sections['planned']);
Assert::same(10, $slidingResult->sections['planned'][0]->service['id']);
Assert::same(PaymentStatus::Planned, $slidingResult->sections['planned'][0]->status);
Assert::same('2026-05-31', $slidingResult->sections['planned'][0]->dueDate);

Assert::count(1, $slidingResult->sections['overdue']);
Assert::same(11, $slidingResult->sections['overdue'][0]->service['id']);
Assert::same(PaymentStatus::Overdue, $slidingResult->sections['overdue'][0]->status);
Assert::same('2026-05-05', $slidingResult->sections['overdue'][0]->dueDate);
