<?php

declare(strict_types=1);

use App\Payment\DashboardItem;
use App\Payment\MonthlyOverview;
use App\Payment\PaymentStatus;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-15');

/**
 * Fixture řádku služby — jen pole, která MonthlyOverview čte (žádná DB).
 * @return array<string, mixed>
 */
function service(
	int $id,
	string $period,
	int $dueDay,
	int $amount,
	int $sortOrder,
	?int $dueMonth = null,
	int $isSliding = 0,
): array {
	return [
		'id' => $id,
		'name' => "Služba {$id}",
		'icon' => null,
		'period' => $period,
		'due_day' => $dueDay,
		'due_month' => $dueMonth,
		'amount' => $amount,
		'sort_order' => $sortOrder,
		'is_sliding' => $isSliding,
	];
}

/**
 * Fixture řádku platby za období 2026-07.
 * @return array<string, mixed>
 */
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
		'note' => null,
	];
}

$services = [
	service(1, 'monthly', 5, 10000, 1),   // due 2026-07-05 < dnes, bez platby -> po splatnosti (virtuální)
	service(2, 'monthly', 20, 20000, 2),  // due 2026-07-20 >= dnes, bez platby -> naplánováno (virtuální)
	service(3, 'monthly', 10, 30000, 3),  // má zaplacenou platbu
	service(4, 'monthly', 25, 40000, 4),  // má přeskočenou platbu
	service(5, 'yearly', 1, 50000, 5, 7), // roční, due_month 7 -> patří sem, due 2026-07-01 < dnes -> po splatnosti
	service(6, 'yearly', 3, 60000, 6, 3), // roční, due_month 3 -> do července NEpatří
	service(7, 'monthly', 8, 70000, 7),   // platba s paid_date I skipped_at -> žebříček: zaplaceno vyhrává
];

$payments = [
	payment(3, '2026-07-10', 30000, '2026-07-09', null),
	payment(4, '2026-07-25', 40000, null, '2026-07-02'),
	payment(7, '2026-07-08', 70000, '2026-07-06', '2026-07-05'), // oba příznaky -> Paid
];

$result = MonthlyOverview::build(2026, 7, $today, $services, $payments);

// --- Sekce & zařazení ---
// Po splatnosti: služby 1 (07-05) a 5 (07-01); řazení due_date vzestupně -> 5 před 1.
Assert::same(['5', '1'], array_map(
	static fn(DashboardItem $i): string => (string) $i->service['id'],
	$result->sections['overdue'],
));
Assert::same(PaymentStatus::Overdue, $result->sections['overdue'][0]->status);

// Naplánováno: služba 2.
Assert::count(1, $result->sections['planned']);
Assert::same(2, $result->sections['planned'][0]->service['id']);
Assert::same(PaymentStatus::Planned, $result->sections['planned'][0]->status);

// Zaplaceno: služby 7 a 3 (7 díky žebříčku, i když má i skipped_at); řazení due_date
// vzestupně -> 7 (07-08) před 3 (07-10).
Assert::same([7, 3], array_map(
	static fn(DashboardItem $i): int => (int) $i->service['id'],
	$result->sections['paid'],
));

// Přeskočeno: služba 4.
Assert::count(1, $result->sections['skipped']);
Assert::same(4, $result->sections['skipped'][0]->service['id']);

// Roční služba 6 (jiný due_month) se do července vůbec nedostala — nikde.
$allIds = [];
foreach ($result->sections as $section) {
	foreach ($section as $item) {
		$allIds[] = (int) $item->service['id'];
	}
}
Assert::false(in_array(6, $allIds, true));
Assert::count(6, $allIds); // 7 služeb minus roční služba 6 mimo období

// --- Virtuální položka (bez platby) dopočte due_date a částku ze šablony ---
$planned = $result->sections['planned'][0];
Assert::same('2026-07-20', $planned->dueDate);
Assert::same(20000, $planned->amount);

// Existující platba drží svůj snapshot (částku z řádku, ne ze šablony). Služba 3 je v pořadí
// druhá (index 1) — až za službou 7 s dřívějším due_date.
$paidThree = $result->sections['paid'][1];
Assert::same(3, $paidThree->service['id']);
Assert::same(30000, $paidThree->amount);
Assert::same('2026-07-10', $paidThree->dueDate);

// --- Součty ---
// Zbývá zaplatit = po splatnosti (10000 + 50000) + naplánováno (20000) = 80000.
Assert::same(80000, $result->remainingTotal);
// Zaplaceno = 30000 + 70000 = 100000.
Assert::same(100000, $result->paidTotal);
// Přeskočené (40000) nejsou ani v jednom součtu — ověřeno tím, že součty výše sedí přesně.

// --- Prázdný vstup ---
$empty = MonthlyOverview::build(2026, 7, $today, [], []);
Assert::same([], $empty->sections['overdue']);
Assert::same(0, $empty->remainingTotal);
Assert::same(0, $empty->paidTotal);

// --- Klouzavá služba nikdy Overdue (i ve zcela minulém, "dnes" 2026-07-15 přesahujícím období) ---
$slidingServices = [
	service(10, 'monthly', 5, 10000, 1, isSliding: 1),  // klouzavá, bez platby, období DÁVNO v minulosti
	service(11, 'monthly', 5, 10000, 2, isSliding: 0),  // běžná (kontrola regresu), stejné parametry
];
$slidingResult = MonthlyOverview::build(2026, 5, $today, $slidingServices, []);

// Klouzavá zůstává naplánováno, přestože je "dnes" hluboko za koncem období.
Assert::count(1, $slidingResult->sections['planned']);
Assert::same(10, $slidingResult->sections['planned'][0]->service['id']);
Assert::same(PaymentStatus::Planned, $slidingResult->sections['planned'][0]->status);
// Klouzavá virtuální položka má due_date dopočtené na poslední den měsíce (řazení "na konec").
Assert::same('2026-05-31', $slidingResult->sections['planned'][0]->dueDate);

// Běžná služba se stejným due_day za stejné (minulé) období naopak Overdue je.
Assert::count(1, $slidingResult->sections['overdue']);
Assert::same(11, $slidingResult->sections['overdue'][0]->service['id']);
Assert::same(PaymentStatus::Overdue, $slidingResult->sections['overdue'][0]->status);
Assert::same('2026-05-05', $slidingResult->sections['overdue'][0]->dueDate);
