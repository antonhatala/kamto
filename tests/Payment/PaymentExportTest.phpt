<?php

declare(strict_types=1);

use App\Payment\PaymentExport;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/**
 * Fixture řádku služby — jen pole, která PaymentExport čte (žádná DB).
 * @return array<string, mixed>
 */
function service(int $id, string $name, ?int $categoryId = null, int $isSliding = 0): array
{
	return ['id' => $id, 'name' => $name, 'category_id' => $categoryId, 'is_sliding' => $isSliding];
}

/**
 * Fixture řádku platby.
 * @return array<string, mixed>
 */
function payment(
	int $serviceId,
	int $year,
	int $month,
	int $amount,
	string $dueDate,
	?string $paidDate = null,
	?string $skippedAt = null,
): array {
	return [
		'service_id' => $serviceId,
		'period_year' => $year,
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

// --- Hlavička — přesně dle zadání, tento pořádek jde přímo do CsvExporter::export(). ---
Assert::same(
	['Služba', 'Kategorie', 'Rok', 'Měsíc', 'Splatnost', 'Zaplaceno', 'Stav', 'Částka (Kč)'],
	PaymentExport::Header,
);

// --- E1 Prázdný rok — žádné platby, žádný pád, prázdné řádky. ---
Assert::same([], PaymentExport::buildRows($today, [], [], []));

// --- Základní scénář — jeden řádek na kategorii, den, stav i pořadí zachováno. ---
$services = [
	service(1, 'Netflix', 1),
	service(2, 'Nájem', null), // bez kategorie
];
$categories = [category(1, 'Zábava', '#2168a3')];
$payments = [
	payment(1, 2026, 1, 19950, '2026-01-15', paidDate: '2026-01-10'),           // zaplaceno
	payment(2, 2026, 1, 1200000, '2026-01-05', skippedAt: '2026-01-05'),        // přeskočeno
	payment(1, 2026, 6, 19950, '2026-06-01'),                                  // po splatnosti (dnes 7.7.)
	payment(1, 2026, 8, 19950, '2026-08-15'),                                  // naplánováno (v budoucnu)
];

$rows = PaymentExport::buildRows($today, $services, $payments, $categories);
Assert::count(4, $rows);

// Zaplaceno — kategorie, měsíc jménem, splatnost/zaplaceno jako ISO string, částka bez haléřů/Kč.
Assert::same(['Netflix', 'Zábava', '2026', 'Leden', '2026-01-15', '2026-01-10', 'Zaplaceno', '199,50'], $rows[0]);

// Přeskočeno — "Zaplaceno" sloupec prázdný (paid_date NULL), i když je částka celá koruna bez haléřů,
// "Bez kategorie" pro službu bez kategorie.
Assert::same(['Nájem', 'Bez kategorie', '2026', 'Leden', '2026-01-05', '', 'Přeskočeno', '12000'], $rows[1]);

// Po splatnosti — due_date < dnes (2026-07-08), obě data NULL.
Assert::same(['Netflix', 'Zábava', '2026', 'Červen', '2026-06-01', '', 'Po splatnosti', '199,50'], $rows[2]);

// Naplánováno — due_date v budoucnu.
Assert::same(['Netflix', 'Zábava', '2026', 'Srpen', '2026-08-15', '', 'Naplánováno', '199,50'], $rows[3]);

// --- Pořadí řádků 1:1 kopíruje pořadí vstupních plateb (PaymentRepository::findByYear už řadí). ---
$reordered = [$payments[3], $payments[0]];
$reorderedRows = PaymentExport::buildRows($today, $services, $reordered, $categories);
Assert::same('Srpen', $reorderedRows[0][3]);
Assert::same('Leden', $reorderedRows[1][3]);

// --- E2 Osiřelá platba (obranná pojistka, FK CASCADE to prakticky vylučuje) — chybějící
// služba nespadne, jméno prázdné, kategorie "Bez kategorie". ---
$orphan = PaymentExport::buildRows($today, [], [payment(999, 2026, 1, 100, '2026-01-01', paidDate: '2026-01-01')], $categories);
Assert::same(['', 'Bez kategorie', '2026', 'Leden', '2026-01-01', '2026-01-01', 'Zaplaceno', '1'], $orphan[0]);

// --- E3 Smazaná/chybějící kategorie (FK ON DELETE SET NULL to vylučuje, ale defenzivně) —
// service.category_id ukazuje na neexistující kategorii -> "Bez kategorie", ne pád. ---
$dangling = [service(1, 'Kabelovka', 999)];
$danglingRows = PaymentExport::buildRows($today, $dangling, [payment(1, 2026, 1, 1000, '2026-01-01', paidDate: '2026-01-01')], $categories);
Assert::same('Bez kategorie', $danglingRows[0][1]);

// --- Klouzavá služba (is_sliding=1) — nezaplacený/nepřeskočený řádek s due_date v minulosti
// exportuje se jako "Naplánováno", ne "Po splatnosti" (dnes je 2026-07-08). ---
$slidingServices = [service(1, 'Nepravidelná platba', isSliding: 1)];
$slidingRows = PaymentExport::buildRows(
	$today,
	$slidingServices,
	[payment(1, 2026, 1, 5000, '2026-01-15')], // dávno po splatnosti, ale klouzavá
	[],
);
Assert::same(['Nepravidelná platba', 'Bez kategorie', '2026', 'Leden', '2026-01-15', '', 'Naplánováno', '50'], $slidingRows[0]);
