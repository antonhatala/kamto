<?php

declare(strict_types=1);

use App\Payment\YearSummary;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/** @return array<string, mixed> */
function service(
	int $id,
	string $period,
	int $amount,
	?int $categoryId = null,
	bool $isArchived = false,
	?int $dueMonth = null,
): array {
	return [
		'id' => $id,
		'name' => "Služba {$id}",
		'amount' => $amount,
		'period' => $period,
		'due_month' => $dueMonth,
		'category_id' => $categoryId,
		'is_archived' => $isArchived ? 1 : 0,
	];
}

/** @return array<string, mixed> */
function payment(int $serviceId, int $year, int $month, int $amount, ?string $paidDate): array
{
	return [
		'service_id' => $serviceId,
		'period_year' => $year,
		'period_month' => $month,
		'due_date' => sprintf('%04d-%02d-15', $year, $month),
		'paid_date' => $paidDate,
		'skipped_at' => null,
		'amount' => $amount,
	];
}

/** @return array<string, mixed> */
function category(int $id, string $name, string $color): array
{
	return ['id' => $id, 'name' => $name, 'color' => $color];
}

$empty = YearSummary::build(2026, $today, [], [], []);
Assert::same(0, $empty->paidThisYear);
Assert::same(0, $empty->averagePerMonth);
Assert::same(0, $empty->monthlyCommitment);
Assert::same(0, $empty->yearlyCommitmentEstimate);
Assert::same(0, $empty->activeServiceCount);
Assert::same([], $empty->categoryBreakdown);

$categories = [
	category(1, 'Bydlení', '#c1622e'),
	category(2, 'Zábava', '#2168a3'),
];

$services = [
	service(1, 'monthly', 10000, 1),
	service(2, 'monthly', 20000, 2),
	service(3, 'yearly', 120000, 1, dueMonth: 3),
	service(4, 'monthly', 50000, null, isArchived: true),
];

$payments = [
	payment(1, 2026, 1, 10000, '2026-01-05'),
	payment(1, 2026, 2, 10000, '2026-02-05'),
	payment(2, 2026, 1, 20000, '2026-01-10'),
	payment(3, 2026, 3, 120000, '2026-03-15'),
	payment(4, 2026, 4, 50000, '2026-04-01'),
	payment(2, 2026, 7, 20000, null),
];

$result = YearSummary::build(2026, $today, $services, $payments, $categories);

Assert::same(210000, $result->paidThisYear);

Assert::same([1 => 30000, 2 => 10000, 3 => 120000, 4 => 50000, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0], $result->paidByMonth);
Assert::same($result->paidThisYear, array_sum($result->paidByMonth));

Assert::same(30000, $result->averagePerMonth);

Assert::same(30000, $result->monthlyCommitment);

Assert::same(480000, $result->yearlyCommitmentEstimate);

Assert::same(3, $result->activeServiceCount);

Assert::count(3, $result->categoryBreakdown);
Assert::same('Bydlení', $result->categoryBreakdown[0]->category->name);
Assert::same(140000, $result->categoryBreakdown[0]->amount);
Assert::same('Bez kategorie', $result->categoryBreakdown[1]->category->name);
Assert::same('#a8a29e', $result->categoryBreakdown[1]->category->color);
Assert::same(50000, $result->categoryBreakdown[1]->amount);
Assert::same('Zábava', $result->categoryBreakdown[2]->category->name);
Assert::same(20000, $result->categoryBreakdown[2]->amount);

$tieCategories = [category(1, 'Zábava', '#2168a3'), category(2, 'Bydlení', '#c1622e')];
$tieServices = [service(1, 'monthly', 10000, 1), service(2, 'monthly', 10000, 2)];
$tiePayments = [payment(1, 2026, 1, 10000, '2026-01-01'), payment(2, 2026, 1, 10000, '2026-01-01')];
$tieResult = YearSummary::build(2026, $today, $tieServices, $tiePayments, $tieCategories);
Assert::same(['Bydlení', 'Zábava'], array_map(static fn($i) => $i->category->name, $tieResult->categoryBreakdown));

$past = YearSummary::build(2025, $today, $services, [payment(1, 2025, 1, 10000, '2025-01-05')], $categories);
Assert::same(10000, $past->paidThisYear);
Assert::same(833, $past->averagePerMonth);

$future = YearSummary::build(2027, $today, $services, [], $categories);
Assert::same(0, $future->paidThisYear);
Assert::same(0, $future->averagePerMonth);

$dangling = [service(9, 'monthly', 1000, 999)];
$danglingResult = YearSummary::build(2026, $today, $dangling, [payment(9, 2026, 1, 1000, '2026-01-01')], $categories);
Assert::same('Bez kategorie', $danglingResult->categoryBreakdown[0]->category->name);
Assert::same('#a8a29e', $danglingResult->categoryBreakdown[0]->category->color);

$orphanServices = [service(1, 'yearly', 60000, 1, dueMonth: 5)];
$orphanPayments = [payment(1, 2026, 3, 60000, '2026-03-10')];
$orphanResult = YearSummary::build(2026, $today, $orphanServices, $orphanPayments, $categories);
Assert::same(60000, $orphanResult->paidThisYear);
Assert::same(60000, $orphanResult->paidByMonth[3]);
Assert::same(0, $orphanResult->paidByMonth[5]);
Assert::same($orphanResult->paidThisYear, array_sum($orphanResult->paidByMonth));
