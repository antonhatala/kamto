<?php

declare(strict_types=1);

use App\Payment\CellState;
use App\Payment\YearHeatmap;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/**
 * Fixture řádku služby — jen pole, která YearHeatmap/PaymentCell čtou (žádná DB).
 * @return array<string, mixed>
 */
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

/**
 * Fixture řádku platby.
 * @return array<string, mixed>
 */
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
		'note' => null,
	];
}

/** @return array<string, mixed> */
function category(int $id, string $name, string $color): array
{
	return ['id' => $id, 'name' => $name, 'color' => $color];
}

// --- E1 Prázdný rok (žádné služby) — žádné řádky, žádný pád. ---
$empty = YearHeatmap::build(2026, $today, [], [], []);
Assert::same([], $empty->rows);

$categories = [category(1, 'Bydlení', '#c1622e')];

// Měsíční služba A (kategorie 1): pokrývá všechny 4 odvozené stavy + mezery.
$serviceA = service(1, 'monthly', categoryId: 1, dueDay: 2);
// Roční služba B (due_month červen): živá buňka jen v červnu.
$serviceB = service(2, 'yearly', dueMonth: 6, dueDay: 1);
// Služba C: archivovaná, ale MÁ platby dřív v roce (archivace uprostřed roku) -> řádek zůstane.
$serviceC = service(3, 'monthly', isArchived: true, dueDay: 3);
// Služba D: archivovaná a BEZ plateb v roce -> skrytá.
$serviceD = service(4, 'monthly', isArchived: true, dueDay: 4);
// Služba E: aktivní, ale vznikla AŽ v roce 2027 (po roce 2026) -> skrytá.
$serviceE = service(5, 'monthly', createdAt: '2027-03-01T00:00:00+01:00', dueDay: 5);
// Služba F: dangling/NULL kategorie (id 999 v seznamu kategorií neexistuje).
$serviceF = service(6, 'monthly', categoryId: 999, dueDay: 6);
// Služby X/Y: shodný due_day (10) -> tie-break usortu musí sáhnout po id (X má nižší id než Y).
$serviceX = service(9, 'monthly', dueDay: 10);
$serviceY = service(10, 'monthly', dueDay: 10);
// Služba Slide: klouzavá (is_sliding=1) s nízkým placeholder due_day (1) — přesto musí skončit
// jako POSLEDNÍ řádek (usort komparátor v YearHeatmap::build řadí primárně podle is_sliding,
// ne podle due_day) — bez tohoto scénáře by regrese na [due_day, id] testem neprošla.
$serviceSlide = service(8, 'monthly', dueDay: 1, isSliding: 1);

$payments = [
	payment(1, 1, '2026-01-15', 10000, '2026-01-10'),          // leden -> Paid
	payment(1, 2, '2026-02-15', 10000, null, '2026-02-01'),    // únor -> Skipped
	payment(1, 3, '2026-03-15', 10000, null, null),            // březen, due < dnes -> Overdue
	// duben..červenec (kromě března výše) bez řádku -> Gap
	payment(1, 8, '2026-08-15', 10000, null, null),            // srpen, due >= dnes -> Planned

	payment(2, 6, '2026-06-01', 500000, '2026-06-01'),         // roční služba B, due_month 6 -> Paid

	payment(3, 1, '2026-01-15', 20000, '2026-01-10'),          // archivovaná služba C — leden Paid
	payment(3, 2, '2026-02-15', 20000, '2026-02-10'),          // — únor Paid, pak archivace, zbytek mezery

	payment(6, 1, '2026-01-15', 5000, '2026-01-10'),           // služba F (dangling kategorie) — Paid
];

$result = YearHeatmap::build(
	2026,
	$today,
	[$serviceA, $serviceB, $serviceC, $serviceD, $serviceE, $serviceF, $serviceX, $serviceY, $serviceSlide],
	$payments,
	$categories,
);

// E2/E3 — archivovaná bez plateb (D) a služba vzniklá po roce (E) jsou skryté.
$visibleIds = array_map(static fn($row) => (int) $row->service['id'], $result->rows);
Assert::false(in_array(4, $visibleIds, true));
Assert::false(in_array(5, $visibleIds, true));
Assert::count(7, $result->rows); // A, B, C, F, X, Y, Slide

// Řazeno dle is_sliding, due_day, id -> neklouzavé dle due_day (B=1, A=2, C=3, F=6, X/Y=10
// s tie-breakem X<Y podle id), klouzavá Slide AŽ NA KONCI navzdory nejnižšímu due_day (1).
Assert::same([2, 1, 3, 6, 9, 10, 8], $visibleIds);

// --- Řádek A — všech 12 buněk, přesné stavy + mezery. ---
$rowA = $result->rows[1];
Assert::same(1, $rowA->service['id']);
Assert::count(12, $rowA->cells);
Assert::same(CellState::Paid, $rowA->cells[0]->state);    // leden
Assert::same(10000, $rowA->cells[0]->amount);
Assert::same(CellState::Skipped, $rowA->cells[1]->state); // únor
Assert::same(CellState::Overdue, $rowA->cells[2]->state); // březen
Assert::same(CellState::Gap, $rowA->cells[3]->state);      // duben — žádný řádek -> klidná pauza
Assert::null($rowA->cells[3]->amount);
Assert::same(CellState::Planned, $rowA->cells[7]->state); // srpen, due >= dnes
Assert::same('Bydlení', $rowA->category->name);
Assert::same('Měsíčně', $rowA->periodBadge);
Assert::false($rowA->isArchived);

// --- Řádek B — roční služba: živá buňka JEN v červnu (index 5), ostatních 11 = Inactive. ---
$rowB = $result->rows[0];
Assert::same(2, $rowB->service['id']);
Assert::same(CellState::Paid, $rowB->cells[5]->state); // červen
foreach ([0, 1, 2, 3, 4, 6, 7, 8, 9, 10, 11] as $i) {
	Assert::same(CellState::Inactive, $rowB->cells[$i]->state);
	Assert::null($rowB->cells[$i]->amount);
}
Assert::same('Ročně', $rowB->periodBadge);

// --- Řádek C — archivace uprostřed roku: leden/únor Paid (před archivací), zbytek mezery. ---
$rowC = $result->rows[2];
Assert::true($rowC->isArchived);
Assert::same(CellState::Paid, $rowC->cells[0]->state);
Assert::same(CellState::Paid, $rowC->cells[1]->state);
Assert::same(CellState::Gap, $rowC->cells[2]->state); // březen a dál — po archivaci, žádné řádky

// --- Řádek F — NULL/smazaná (dangling) kategorie -> neutrální fallback, žádný pád na hexu. ---
$rowF = $result->rows[3];
Assert::same('Bez kategorie', $rowF->category->name);
Assert::same('#a8a29e', $rowF->category->color);

// --- Řádky X/Y — shodný due_day (10), tie-break usortu podle id (X=9 před Y=10). ---
Assert::same(9, $result->rows[4]->service['id']);
Assert::same(10, $result->rows[5]->service['id']);

// --- Řádek Slide — klouzavá služba je vždy POSLEDNÍ řádek, i s nejnižším due_day ze všech. ---
$rowSlide = $result->rows[6];
Assert::same(8, $rowSlide->service['id']);
Assert::same(1, $rowSlide->service['due_day']);
Assert::same(1, $rowSlide->service['is_sliding']);

// --- E4 Přestupný rok — vždy přesně 12 sloupců bez ohledu na počet dní v únoru. ---
$leapResult = YearHeatmap::build(2024, $today, [$serviceA], [], $categories);
Assert::count(12, $leapResult->rows[0]->cells);
Assert::same(CellState::Gap, $leapResult->rows[0]->cells[1]->state); // únor 2024 bez plateb -> Gap

// --- QA#1 „osiřelá" platba viditelná v heatmapě — roční služba má dnes due_month 6, ale
// existuje zaplacená platba za březen (due_month se změnil po zaplacení). Payment řádek je
// ground truth: březnová buňka je Paid (ne Inactive), aby platba nezmizela z heatmapy. Buňka
// v aktuálním due_month bez platby zůstává Inactive. ---
$orphanService = service(7, 'yearly', dueMonth: 6);
$orphanPayments = [payment(7, 3, '2026-03-15', 60000, '2026-03-10')]; // březen != due_month 6
$orphanResult = YearHeatmap::build(2026, $today, [$orphanService], $orphanPayments, $categories);
Assert::count(1, $orphanResult->rows);
$orphanRow = $orphanResult->rows[0];
Assert::same(CellState::Paid, $orphanRow->cells[2]->state); // březen (index 2) — z platby, ne Inactive
Assert::same(60000, $orphanRow->cells[2]->amount);
Assert::same(CellState::Gap, $orphanRow->cells[5]->state);      // červen (due_month) bez platby -> Gap (klidná pauza)
Assert::same(CellState::Inactive, $orphanRow->cells[0]->state); // leden — jiný měsíc bez platby -> Inactive
