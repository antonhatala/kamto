<?php

declare(strict_types=1);

use App\Payment\CellState;
use App\Payment\PaymentStatus;
use App\Payment\ServiceHistory;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/** @return array<string, mixed> */
function payment(int $year, int $month, string $dueDate, int $amount, ?string $paidDate, ?string $skippedAt): array
{
	return [
		'period_year' => $year,
		'period_month' => $month,
		'due_date' => $dueDate,
		'paid_date' => $paidDate,
		'skipped_at' => $skippedAt,
		'amount' => $amount,
	];
}

$monthlyService = ['id' => 1, 'name' => 'Internet', 'period' => 'monthly', 'due_month' => null];
$emptyResult = ServiceHistory::build($today, $monthlyService, []);
Assert::same([], $emptyResult->payments);
Assert::same(0, $emptyResult->paidCount);
Assert::same(0, $emptyResult->paidTotal);
Assert::same(0, $emptyResult->averagePaidAmount);
Assert::same(0, $emptyResult->skippedCount);
Assert::null($emptyResult->firstPeriod);
Assert::null($emptyResult->lastPeriod);
Assert::same([], $emptyResult->heatmapYears);

$payments = [
	payment(2024, 1, '2024-01-15', 10000, '2024-01-10', null),
	payment(2024, 6, '2024-06-15', 10000, null, '2024-06-01'),
	payment(2024, 12, '2024-12-15', 10000, null, null),
	payment(2026, 1, '2026-01-15', 12000, '2026-01-10', null),
	payment(2026, 8, '2026-08-15', 12000, null, null),
];

$result = ServiceHistory::build($today, $monthlyService, $payments);

Assert::same(2, $result->paidCount);
Assert::same(22000, $result->paidTotal);
Assert::same(11000, $result->averagePaidAmount);
Assert::same(1, $result->skippedCount);

Assert::same(['year' => 2024, 'month' => 1], $result->firstPeriod);
Assert::same(['year' => 2026, 'month' => 8], $result->lastPeriod);

Assert::count(5, $result->payments);
Assert::same(['year' => 2026, 'month' => 8], ['year' => $result->payments[0]->periodYear, 'month' => $result->payments[0]->periodMonth]);
Assert::same(PaymentStatus::Planned, $result->payments[0]->status);
Assert::same(['year' => 2024, 'month' => 1], ['year' => $result->payments[4]->periodYear, 'month' => $result->payments[4]->periodMonth]);
Assert::same(PaymentStatus::Paid, $result->payments[4]->status);

Assert::count(3, $result->heatmapYears);
Assert::same(2024, $result->heatmapYears[0]->year);
Assert::same(2025, $result->heatmapYears[1]->year);
Assert::same(2026, $result->heatmapYears[2]->year);
foreach ($result->heatmapYears as $yearRow) {
	Assert::count(12, $yearRow->cells);
}

$year2024 = $result->heatmapYears[0];
Assert::same(CellState::Paid, $year2024->cells[0]->state);
Assert::same(CellState::Skipped, $year2024->cells[5]->state);
Assert::same(CellState::Overdue, $year2024->cells[11]->state);
Assert::same(CellState::Gap, $year2024->cells[1]->state);

$year2025 = $result->heatmapYears[1];
foreach ($year2025->cells as $cell) {
	Assert::same(CellState::Gap, $cell->state);
	Assert::null($cell->amount);
}

$year2026 = $result->heatmapYears[2];
Assert::same(CellState::Paid, $year2026->cells[0]->state);
Assert::same(CellState::Planned, $year2026->cells[7]->state);

$yearlyService = ['id' => 2, 'name' => 'Doména', 'period' => 'yearly', 'due_month' => 3];
$yearlyPayments = [payment(2025, 3, '2025-03-15', 50000, '2025-03-10', null)];
$yearlyResult = ServiceHistory::build($today, $yearlyService, $yearlyPayments);

Assert::count(1, $yearlyResult->heatmapYears);
$yearlyYear = $yearlyResult->heatmapYears[0];
Assert::same(2025, $yearlyYear->year);
Assert::same(CellState::Paid, $yearlyYear->cells[2]->state);
foreach ([0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11] as $i) {
	Assert::same(CellState::Inactive, $yearlyYear->cells[$i]->state);
}
Assert::same(1, $yearlyResult->paidCount);
Assert::same(50000, $yearlyResult->paidTotal);

$slidingService = ['id' => 3, 'name' => 'Nepravidelná platba', 'period' => 'monthly', 'due_month' => null, 'is_sliding' => 1];
$slidingPayments = [payment(2024, 12, '2024-12-15', 5000, null, null)];
$slidingResult = ServiceHistory::build($today, $slidingService, $slidingPayments);

Assert::count(1, $slidingResult->payments);
Assert::same(PaymentStatus::Planned, $slidingResult->payments[0]->status);

$slidingYear = $slidingResult->heatmapYears[0];
Assert::same(2024, $slidingYear->year);
Assert::same(CellState::Planned, $slidingYear->cells[11]->state);
