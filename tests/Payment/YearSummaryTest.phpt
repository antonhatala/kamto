<?php

declare(strict_types=1);

use App\Payment\YearSummary;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/**
 * Fixture řádku služby — jen pole, která YearSummary čte (žádná DB).
 * @return array<string, mixed>
 */
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

/**
 * Fixture řádku platby.
 * @return array<string, mixed>
 */
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
		'note' => null,
	];
}

/** @return array<string, mixed> */
function category(int $id, string $name, string $color): array
{
	return ['id' => $id, 'name' => $name, 'color' => $color];
}

// --- E1 Prázdný rok — žádné dělení nulou, vše na 0. ---
$empty = YearSummary::build(2026, $today, [], [], []);
Assert::same(0, $empty->paidThisYear);
Assert::same(0, $empty->averagePerMonth);
Assert::same(0, $empty->monthlyCommitment);
Assert::same(0, $empty->yearlyCommitmentEstimate);
Assert::same(0, $empty->activeServiceCount);
Assert::same([], $empty->categoryBreakdown);

// --- Základní scénář ---
$categories = [
	category(1, 'Bydlení', '#c1622e'),
	category(2, 'Zábava', '#2168a3'),
];

$services = [
	service(1, 'monthly', 10000, 1),                      // aktivní, měsíční, kategorie 1
	service(2, 'monthly', 20000, 2),                      // aktivní, měsíční, kategorie 2
	service(3, 'yearly', 120000, 1, dueMonth: 3),          // aktivní, roční, kategorie 1
	service(4, 'monthly', 50000, null, isArchived: true), // E2 archivovaná, bez kategorie
];

$payments = [
	payment(1, 2026, 1, 10000, '2026-01-05'),
	payment(1, 2026, 2, 10000, '2026-02-05'),
	payment(2, 2026, 1, 20000, '2026-01-10'),
	payment(3, 2026, 3, 120000, '2026-03-15'),
	payment(4, 2026, 4, 50000, '2026-04-01'), // archivovaná služba, ale ZAPLACENO -> patří do "letos"
	payment(2, 2026, 7, 20000, null),          // nezaplaceno -> nepočítá se do paidThisYear
];

$result = YearSummary::build(2026, $today, $services, $payments, $categories);

// Zaplaceno letos = 10000+10000+20000+120000+50000 = 210000 (vč. archivované služby 4 — E2).
Assert::same(210000, $result->paidThisYear);

// paidByMonth — rozpad zaplaceného dle period_month PLATBY (ne dle buněk heatmapy):
// leden 10000+20000=30000, únor 10000, březen 120000 (roční služba 3 do svého due_month),
// duben 50000 (archivovaná služba 4). Index 1–12, ostatní měsíce 0.
Assert::same([1 => 30000, 2 => 10000, 3 => 120000, 4 => 50000, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0], $result->paidByMonth);
// Invariant: součet grafu === headline "Zaplaceno letos" VŽDY (počítáno ze stejných plateb).
Assert::same($result->paidThisYear, array_sum($result->paidByMonth));

// Průměr/měsíc = zaplaceno / uplynulé měsíce (dnes 2026-07-08 -> 7 měsíců uplynulo).
Assert::same(30000, $result->averagePerMonth);

// Měsíční závazek = jen aktivní měsíční služby (1, 2) = 10000 + 20000 = 30000 — E2 archivovaná
// služba 4 se do závazku NEPOČÍTÁ, přestože její platba patřila do "letos zaplaceno".
Assert::same(30000, $result->monthlyCommitment);

// Odhad/rok = 30000*12 + roční aktivní (služba 3, 120000) = 360000 + 120000 = 480000.
Assert::same(480000, $result->yearlyCommitmentEstimate);

// Aktivních služeb = 3 (služba 4 je archivovaná).
Assert::same(3, $result->activeServiceCount);

// Rozpad podle kategorie: kategorie 1 (10000+10000+120000=140000), bez kategorie (50000,
// archivovaná služba 4 — E3 NULL kategorie), kategorie 2 (20000) — seřazeno sestupně dle částky.
Assert::count(3, $result->categoryBreakdown);
Assert::same('Bydlení', $result->categoryBreakdown[0]->category->name);
Assert::same(140000, $result->categoryBreakdown[0]->amount);
Assert::same('Bez kategorie', $result->categoryBreakdown[1]->category->name);
Assert::same('#a8a29e', $result->categoryBreakdown[1]->category->color);
Assert::same(50000, $result->categoryBreakdown[1]->amount);
Assert::same('Zábava', $result->categoryBreakdown[2]->category->name);
Assert::same(20000, $result->categoryBreakdown[2]->amount);

// --- Shodná částka -> tie-break abecedně dle jména kategorie (deterministické pořadí). ---
$tieCategories = [category(1, 'Zábava', '#2168a3'), category(2, 'Bydlení', '#c1622e')];
$tieServices = [service(1, 'monthly', 10000, 1), service(2, 'monthly', 10000, 2)];
$tiePayments = [payment(1, 2026, 1, 10000, '2026-01-01'), payment(2, 2026, 1, 10000, '2026-01-01')];
$tieResult = YearSummary::build(2026, $today, $tieServices, $tiePayments, $tieCategories);
Assert::same(['Bydlení', 'Zábava'], array_map(static fn($i) => $i->category->name, $tieResult->categoryBreakdown));

// --- E4 Minulý rok — vždy celých 12 uplynulých měsíců bez ohledu na "dnes". ---
$past = YearSummary::build(2025, $today, $services, [payment(1, 2025, 1, 10000, '2025-01-05')], $categories);
Assert::same(10000, $past->paidThisYear);
Assert::same(833, $past->averagePerMonth); // round(10000 / 12)

// --- E5 Budoucí rok — 0 uplynulých měsíců, žádné dělení nulou. ---
$future = YearSummary::build(2027, $today, $services, [], $categories);
Assert::same(0, $future->paidThisYear);
Assert::same(0, $future->averagePerMonth);

// --- E6 NULL/smazaná kategorie (obranná pojistka — FK ON DELETE SET NULL "visící" referenci
// prakticky vylučuje, ale resolve() musí i tak dopadnout na neutrální fallback bez pádu). ---
$dangling = [service(9, 'monthly', 1000, 999)]; // category_id 999 v seznamu kategorií neexistuje
$danglingResult = YearSummary::build(2026, $today, $dangling, [payment(9, 2026, 1, 1000, '2026-01-01')], $categories);
Assert::same('Bez kategorie', $danglingResult->categoryBreakdown[0]->category->name);
Assert::same('#a8a29e', $danglingResult->categoryBreakdown[0]->category->color);

// --- E7 „Osiřelá" roční platba — služba má dnes due_month 5, ale existuje zaplacená platba
// za period_month 3 (perioda/due_month se změnily po zaplacení). paidByMonth řadí platbu dle
// JEJÍHO period_month (3), ne dle aktuálního due_month služby → do grafu se dostane a invariant
// Σ paidByMonth === paidThisYear drží (platba by jinak z dopočtu přes buňky vypadla). ---
$orphanServices = [service(1, 'yearly', 60000, 1, dueMonth: 5)];
$orphanPayments = [payment(1, 2026, 3, 60000, '2026-03-10')]; // period_month 3 != due_month 5
$orphanResult = YearSummary::build(2026, $today, $orphanServices, $orphanPayments, $categories);
Assert::same(60000, $orphanResult->paidThisYear);
Assert::same(60000, $orphanResult->paidByMonth[3]);   // do svého skutečného období, ne do 5
Assert::same(0, $orphanResult->paidByMonth[5]);
Assert::same($orphanResult->paidThisYear, array_sum($orphanResult->paidByMonth));
