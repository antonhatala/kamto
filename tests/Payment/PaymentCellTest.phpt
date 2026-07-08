<?php

declare(strict_types=1);

use App\Payment\CellState;
use App\Payment\PaymentCell;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

$monthly = ['period' => 'monthly', 'due_month' => null];
$yearly = ['period' => 'yearly', 'due_month' => 6];

// Roční služba mimo svůj due_month a BEZ payment řádku -> Inactive (měsíc pro ni neexistuje).
$inactive = PaymentCell::build($yearly, 2026, 5, null, $today);
Assert::same(CellState::Inactive, $inactive->state);
Assert::null($inactive->amount);
Assert::same(2026, $inactive->periodYear);
Assert::same(5, $inactive->periodMonth);

// QA#1 — „osiřelá" platba: roční služba má dnes due_month 6, ale existuje zaplacená platba za
// květen (perioda/due_month se změnily po zaplacení). Payment řádek je ground truth -> buňka
// se odvodí z platby (Paid), NE Inactive — jinak by z heatmapy zmizela, ale v seznamu plateb
// detailu by byla (nesoulad uvnitř stránky).
$orphan = PaymentCell::build($yearly, 2026, 5, [
	'due_date' => '2026-05-15', 'paid_date' => '2026-05-10', 'skipped_at' => null, 'amount' => 60000,
], $today);
Assert::same(CellState::Paid, $orphan->state);
Assert::same(60000, $orphan->amount);

// Žádný payment řádek (a měsíční služba, nebo roční ve svém due_month) -> Gap, ne virtuální
// dopočet (na rozdíl od MonthlyOverview) — "mezera" je vždy klidná pauza.
Assert::same(CellState::Gap, PaymentCell::build($monthly, 2026, 4, null, $today)->state);
Assert::same(CellState::Gap, PaymentCell::build($yearly, 2026, 6, null, $today)->state);

// Existující řádek -> žebříček přes PaymentStatus::derive, převedený na CellState.
$paid = PaymentCell::build($monthly, 2026, 1, [
	'due_date' => '2026-01-15', 'paid_date' => '2026-01-10', 'skipped_at' => null, 'amount' => 10000,
], $today);
Assert::same(CellState::Paid, $paid->state);
Assert::same(10000, $paid->amount);

$skipped = PaymentCell::build($monthly, 2026, 2, [
	'due_date' => '2026-02-15', 'paid_date' => null, 'skipped_at' => '2026-02-01', 'amount' => 10000,
], $today);
Assert::same(CellState::Skipped, $skipped->state);

// Po splatnosti — striktně due_date < dnes (v den splatnosti ještě Planned, stejná hranice
// jako PaymentStatus::derive).
$overdue = PaymentCell::build($monthly, 2026, 3, [
	'due_date' => '2026-07-07', 'paid_date' => null, 'skipped_at' => null, 'amount' => 5000,
], $today);
Assert::same(CellState::Overdue, $overdue->state);

$plannedToday = PaymentCell::build($monthly, 2026, 7, [
	'due_date' => '2026-07-08', 'paid_date' => null, 'skipped_at' => null, 'amount' => 5000,
], $today);
Assert::same(CellState::Planned, $plannedToday->state);

$plannedFuture = PaymentCell::build($monthly, 2026, 8, [
	'due_date' => '2026-08-15', 'paid_date' => null, 'skipped_at' => null, 'amount' => 5000,
], $today);
Assert::same(CellState::Planned, $plannedFuture->state);

// CellState::fromPaymentStatus — přímý 1:1 převod pro všechny 4 stavy platby.
Assert::same(CellState::Paid, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Paid));
Assert::same(CellState::Skipped, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Skipped));
Assert::same(CellState::Overdue, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Overdue));
Assert::same(CellState::Planned, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Planned));
