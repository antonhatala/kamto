<?php

declare(strict_types=1);

use App\Payment\CellState;
use App\Payment\YearHeatmap;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/** @return array<string, mixed> */
function service(
	int $id,
	string $period,
	?int $dueMonth = null,
	?int $categoryId = null,
	bool $isArchived = false,
	string $createdAt = '2020-01-01T00:00:00+01:00',
	int $dueDay = 1,
	int $isSliding = 0,
): array {
	return [
		'id' => $id,
		'name' => "Služba {$id}",
		'period' => $period,
		'due_month' => $dueMonth,
		'category_id' => $categoryId,
		'is_archived' => $isArchived ? 1 : 0,
		'created_at' => $createdAt,
		'due_day' => $dueDay,
		'is_sliding' => $isSliding,
	];
}

/** @return array<string, mixed> */
function payment(int $serviceId, int $month, string $dueDate, int $amount, ?string $paidDate, ?string $skippedAt = null): array
{
	return [
		'service_id' => $serviceId,
		'period_year' => 2026,
		'period_month' => $month,
		'due_date' => $dueDate,
		'paid_date' => $paidDate,
		'skipped_at' => $skippedAt,
		'amount' => $amount,
	];
}

/** @return array<string, mixed> */
function category(int $id, string $name, string $color): array
{
	return ['id' => $id, 'name' => $name, 'color' => $color];
}

$empty = YearHeatmap::build(2026, $today, [], [], []);
Assert::same([], $empty->rows);

$categories = [category(1, 'Bydlení', '#c1622e')];

$serviceA = service(1, 'monthly', categoryId: 1, dueDay: 2);
$serviceB = service(2, 'yearly', dueMonth: 6, dueDay: 1);
$serviceC = service(3, 'monthly', isArchived: true, dueDay: 3);
$serviceD = service(4, 'monthly', isArchived: true, dueDay: 4);
$serviceE = service(5, 'monthly', createdAt: '2027-03-01T00:00:00+01:00', dueDay: 5);
$serviceF = service(6, 'monthly', categoryId: 999, dueDay: 6);
$serviceX = service(9, 'monthly', dueDay: 10);
$serviceY = service(10, 'monthly', dueDay: 10);
$serviceSlide = service(8, 'monthly', dueDay: 1, isSliding: 1);

$payments = [
	payment(1, 1, '2026-01-15', 10000, '2026-01-10'),
	payment(1, 2, '2026-02-15', 10000, null, '2026-02-01'),
	payment(1, 3, '2026-03-15', 10000, null, null),
	payment(1, 8, '2026-08-15', 10000, null, null),

	payment(2, 6, '2026-06-01', 500000, '2026-06-01'),

	payment(3, 1, '2026-01-15', 20000, '2026-01-10'),
	payment(3, 2, '2026-02-15', 20000, '2026-02-10'),

	payment(6, 1, '2026-01-15', 5000, '2026-01-10'),
];

$result = YearHeatmap::build(
	2026,
	$today,
	[$serviceA, $serviceB, $serviceC, $serviceD, $serviceE, $serviceF, $serviceX, $serviceY, $serviceSlide],
	$payments,
	$categories,
);

$visibleIds = array_map(static fn($row) => (int) $row->service['id'], $result->rows);
Assert::false(in_array(4, $visibleIds, true));
Assert::false(in_array(5, $visibleIds, true));
Assert::count(7, $result->rows);

Assert::same([2, 1, 3, 6, 9, 10, 8], $visibleIds);

$rowA = $result->rows[1];
Assert::same(1, $rowA->service['id']);
Assert::count(12, $rowA->cells);
Assert::same(CellState::Paid, $rowA->cells[0]->state);
Assert::same(10000, $rowA->cells[0]->amount);
Assert::same(CellState::Skipped, $rowA->cells[1]->state);
Assert::same(CellState::Overdue, $rowA->cells[2]->state);
Assert::same(CellState::Gap, $rowA->cells[3]->state);
Assert::null($rowA->cells[3]->amount);
Assert::same(CellState::Planned, $rowA->cells[7]->state);
Assert::same('Bydlení', $rowA->category->name);
Assert::same('Měsíčně', $rowA->periodBadge);
Assert::false($rowA->isArchived);

$rowB = $result->rows[0];
Assert::same(2, $rowB->service['id']);
Assert::same(CellState::Paid, $rowB->cells[5]->state);
foreach ([0, 1, 2, 3, 4, 6, 7, 8, 9, 10, 11] as $i) {
	Assert::same(CellState::Inactive, $rowB->cells[$i]->state);
	Assert::null($rowB->cells[$i]->amount);
}
Assert::same('Ročně', $rowB->periodBadge);

$rowC = $result->rows[2];
Assert::true($rowC->isArchived);
Assert::same(CellState::Paid, $rowC->cells[0]->state);
Assert::same(CellState::Paid, $rowC->cells[1]->state);
Assert::same(CellState::Gap, $rowC->cells[2]->state);

$rowF = $result->rows[3];
Assert::same('Bez kategorie', $rowF->category->name);
Assert::same('#a8a29e', $rowF->category->color);

Assert::same(9, $result->rows[4]->service['id']);
Assert::same(10, $result->rows[5]->service['id']);

$rowSlide = $result->rows[6];
Assert::same(8, $rowSlide->service['id']);
Assert::same(1, $rowSlide->service['due_day']);
Assert::same(1, $rowSlide->service['is_sliding']);

$leapResult = YearHeatmap::build(2024, $today, [$serviceA], [], $categories);
Assert::count(12, $leapResult->rows[0]->cells);
Assert::same(CellState::Gap, $leapResult->rows[0]->cells[1]->state);

$orphanService = service(7, 'yearly', dueMonth: 6);
$orphanPayments = [payment(7, 3, '2026-03-15', 60000, '2026-03-10')];
$orphanResult = YearHeatmap::build(2026, $today, [$orphanService], $orphanPayments, $categories);
Assert::count(1, $orphanResult->rows);
$orphanRow = $orphanResult->rows[0];
Assert::same(CellState::Paid, $orphanRow->cells[2]->state);
Assert::same(60000, $orphanRow->cells[2]->amount);
Assert::same(CellState::Gap, $orphanRow->cells[5]->state);
Assert::same(CellState::Inactive, $orphanRow->cells[0]->state);
