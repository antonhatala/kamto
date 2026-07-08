<?php

declare(strict_types=1);

use App\Payment\CellState;
use App\Payment\PaymentStatus;
use App\Payment\ServiceHistory;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/**
 * Fixture řádku platby.
 * @return array<string, mixed>
 */
function payment(int $year, int $month, string $dueDate, int $amount, ?string $paidDate, ?string $skippedAt, ?string $note = null): array
{
	return [
		'period_year' => $year,
		'period_month' => $month,
		'due_date' => $dueDate,
		'paid_date' => $paidDate,
		'skipped_at' => $skippedAt,
		'amount' => $amount,
		'note' => $note,
	];
}

// --- E1 Brand nová služba, žádná platba — žádné dělení nulou, prázdná historie. ---
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

// --- Základní scénář — payments přichází ASC (jako z PaymentRepository::findByService),
// 2024 a 2026 mají historii, 2025 je celý "mezerový" rok uprostřed rozsahu. ---
$payments = [
	payment(2024, 1, '2024-01-15', 10000, '2024-01-10', null),  // Paid
	payment(2024, 6, '2024-06-15', 10000, null, '2024-06-01'),  // Skipped
	payment(2024, 12, '2024-12-15', 10000, null, null),         // due < dnes -> Overdue
	payment(2026, 1, '2026-01-15', 12000, '2026-01-10', null, 'Zdražení'), // Paid, s poznámkou
	payment(2026, 8, '2026-08-15', 12000, null, null),          // due >= dnes -> Planned
];

$result = ServiceHistory::build($today, $monthlyService, $payments);

// Součty: zaplaceno 2× (2024-01 + 2026-01 = 22000), průměr = round(22000/2) = 11000.
Assert::same(2, $result->paidCount);
Assert::same(22000, $result->paidTotal);
Assert::same(11000, $result->averagePaidAmount);
Assert::same(1, $result->skippedCount);

// První/poslední období podle skutečné historie (ne "dnes").
Assert::same(['year' => 2024, 'month' => 1], $result->firstPeriod);
Assert::same(['year' => 2026, 'month' => 8], $result->lastPeriod);

// Seznam plateb — nejnovější nahoře (period_year, period_month desc).
Assert::count(5, $result->payments);
Assert::same(['year' => 2026, 'month' => 8], ['year' => $result->payments[0]->periodYear, 'month' => $result->payments[0]->periodMonth]);
Assert::same(PaymentStatus::Planned, $result->payments[0]->status);
Assert::same(['year' => 2024, 'month' => 1], ['year' => $result->payments[4]->periodYear, 'month' => $result->payments[4]->periodMonth]);
Assert::same(PaymentStatus::Paid, $result->payments[4]->status);
// Poznámka se propisuje na položku historie.
Assert::same('Zdražení', $result->payments[1]->note); // 2026-01, druhá odshora
Assert::null($result->payments[0]->note);

// Mini-heatmapa: 3 roky (2024, 2025 — E2 "mezerový" rok bez jediné platby, 2026), každý 12 sloupců.
Assert::count(3, $result->heatmapYears);
Assert::same(2024, $result->heatmapYears[0]->year);
Assert::same(2025, $result->heatmapYears[1]->year);
Assert::same(2026, $result->heatmapYears[2]->year);
foreach ($result->heatmapYears as $yearRow) {
	Assert::count(12, $yearRow->cells); // E3 přestupný rok mezi nimi (2024) — pořád přesně 12.
}

$year2024 = $result->heatmapYears[0];
Assert::same(CellState::Paid, $year2024->cells[0]->state);   // leden
Assert::same(CellState::Skipped, $year2024->cells[5]->state); // červen
Assert::same(CellState::Overdue, $year2024->cells[11]->state); // prosinec
Assert::same(CellState::Gap, $year2024->cells[1]->state);      // únor — bez řádku

// E2 — celý rok 2025 bez jediné platby je samé mezery (klidná pauza), ne chyba.
$year2025 = $result->heatmapYears[1];
foreach ($year2025->cells as $cell) {
	Assert::same(CellState::Gap, $cell->state);
	Assert::null($cell->amount);
}

$year2026 = $result->heatmapYears[2];
Assert::same(CellState::Paid, $year2026->cells[0]->state);   // leden
Assert::same(CellState::Planned, $year2026->cells[7]->state); // srpen

// --- E4 Roční služba — v mini-heatmapě živá buňka JEN ve svém due_month, zbytek Inactive. ---
$yearlyService = ['id' => 2, 'name' => 'Doména', 'period' => 'yearly', 'due_month' => 3];
$yearlyPayments = [payment(2025, 3, '2025-03-15', 50000, '2025-03-10', null)];
$yearlyResult = ServiceHistory::build($today, $yearlyService, $yearlyPayments);

Assert::count(1, $yearlyResult->heatmapYears); // jen rok 2025 (min==max plateb)
$yearlyYear = $yearlyResult->heatmapYears[0];
Assert::same(2025, $yearlyYear->year);
Assert::same(CellState::Paid, $yearlyYear->cells[2]->state); // březen (index 2)
foreach ([0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11] as $i) {
	Assert::same(CellState::Inactive, $yearlyYear->cells[$i]->state);
}
Assert::same(1, $yearlyResult->paidCount);
Assert::same(50000, $yearlyResult->paidTotal);
