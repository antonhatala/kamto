<?php

declare(strict_types=1);

use App\Payment\PaymentExport;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

/** @return array<string, mixed> */
function service(int $id, string $name, ?int $categoryId = null, int $isSliding = 0): array
{
	return ['id' => $id, 'name' => $name, 'category_id' => $categoryId, 'is_sliding' => $isSliding];
}

/** @return array<string, mixed> */
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

Assert::same(
	['Služba', 'Kategorie', 'Rok', 'Měsíc', 'Splatnost', 'Zaplaceno', 'Stav', 'Částka (Kč)'],
	PaymentExport::Header,
);

Assert::same([], PaymentExport::buildRows($today, [], [], []));

$services = [
	service(1, 'Netflix', 1),
	service(2, 'Nájem', null),
];
$categories = [category(1, 'Zábava', '#2168a3')];
$payments = [
	payment(1, 2026, 1, 19950, '2026-01-15', paidDate: '2026-01-10'),
	payment(2, 2026, 1, 1200000, '2026-01-05', skippedAt: '2026-01-05'),
	payment(1, 2026, 6, 19950, '2026-06-01'),
	payment(1, 2026, 8, 19950, '2026-08-15'),
];

$rows = PaymentExport::buildRows($today, $services, $payments, $categories);
Assert::count(4, $rows);

Assert::same(['Netflix', 'Zábava', '2026', 'Leden', '2026-01-15', '2026-01-10', 'Zaplaceno', '199,50'], $rows[0]);

Assert::same(['Nájem', 'Bez kategorie', '2026', 'Leden', '2026-01-05', '', 'Přeskočeno', '12000'], $rows[1]);

Assert::same(['Netflix', 'Zábava', '2026', 'Červen', '2026-06-01', '', 'Po splatnosti', '199,50'], $rows[2]);

Assert::same(['Netflix', 'Zábava', '2026', 'Srpen', '2026-08-15', '', 'Naplánováno', '199,50'], $rows[3]);

$reordered = [$payments[3], $payments[0]];
$reorderedRows = PaymentExport::buildRows($today, $services, $reordered, $categories);
Assert::same('Srpen', $reorderedRows[0][3]);
Assert::same('Leden', $reorderedRows[1][3]);

$orphan = PaymentExport::buildRows($today, [], [payment(999, 2026, 1, 100, '2026-01-01', paidDate: '2026-01-01')], $categories);
Assert::same(['', 'Bez kategorie', '2026', 'Leden', '2026-01-01', '2026-01-01', 'Zaplaceno', '1'], $orphan[0]);

$dangling = [service(1, 'Kabelovka', 999)];
$danglingRows = PaymentExport::buildRows($today, $dangling, [payment(1, 2026, 1, 1000, '2026-01-01', paidDate: '2026-01-01')], $categories);
Assert::same('Bez kategorie', $danglingRows[0][1]);

$slidingServices = [service(1, 'Nepravidelná platba', isSliding: 1)];
$slidingRows = PaymentExport::buildRows(
	$today,
	$slidingServices,
	[payment(1, 2026, 1, 5000, '2026-01-15')],
	[],
);
Assert::same(['Nepravidelná platba', 'Bez kategorie', '2026', 'Leden', '2026-01-15', '', 'Naplánováno', '50'], $slidingRows[0]);
