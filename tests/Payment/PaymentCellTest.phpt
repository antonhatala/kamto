<?php

declare(strict_types=1);

use App\Payment\CellState;
use App\Payment\PaymentCell;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

$monthly = ['period' => 'monthly', 'due_month' => null];
$yearly = ['period' => 'yearly', 'due_month' => 6];

$inactive = PaymentCell::build($yearly, 2026, 5, null, $today);
Assert::same(CellState::Inactive, $inactive->state);
Assert::null($inactive->amount);
Assert::same(2026, $inactive->periodYear);
Assert::same(5, $inactive->periodMonth);

$orphan = PaymentCell::build($yearly, 2026, 5, [
	'due_date' => '2026-05-15', 'paid_date' => '2026-05-10', 'skipped_at' => null, 'amount' => 60000,
], $today);
Assert::same(CellState::Paid, $orphan->state);
Assert::same(60000, $orphan->amount);

Assert::same(CellState::Gap, PaymentCell::build($monthly, 2026, 4, null, $today)->state);
Assert::same(CellState::Gap, PaymentCell::build($yearly, 2026, 6, null, $today)->state);

$paid = PaymentCell::build($monthly, 2026, 1, [
	'due_date' => '2026-01-15', 'paid_date' => '2026-01-10', 'skipped_at' => null, 'amount' => 10000,
], $today);
Assert::same(CellState::Paid, $paid->state);
Assert::same(10000, $paid->amount);

$skipped = PaymentCell::build($monthly, 2026, 2, [
	'due_date' => '2026-02-15', 'paid_date' => null, 'skipped_at' => '2026-02-01', 'amount' => 10000,
], $today);
Assert::same(CellState::Skipped, $skipped->state);

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

$slidingMonthly = ['period' => 'monthly', 'due_month' => null, 'is_sliding' => 1];
$slidingOverdueCandidate = PaymentCell::build($slidingMonthly, 2026, 3, [
	'due_date' => '2026-03-07', 'paid_date' => null, 'skipped_at' => null, 'amount' => 5000,
], $today);
Assert::same(CellState::Planned, $slidingOverdueCandidate->state);

Assert::same(CellState::Gap, PaymentCell::build($slidingMonthly, 2026, 4, null, $today)->state);

Assert::same(CellState::Paid, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Paid));
Assert::same(CellState::Skipped, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Skipped));
Assert::same(CellState::Overdue, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Overdue));
Assert::same(CellState::Planned, CellState::fromPaymentStatus(App\Payment\PaymentStatus::Planned));
