<?php

declare(strict_types=1);

use App\Payment\PaymentStatus;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

Assert::same(
	PaymentStatus::Paid,
	PaymentStatus::derive('2026-07-01', '2026-07-01', '2026-07-05', $today),
);
Assert::same(
	PaymentStatus::Paid,
	PaymentStatus::derive('2026-07-01', null, '2026-01-01', $today),
);

Assert::same(
	PaymentStatus::Skipped,
	PaymentStatus::derive(null, '2026-07-01', '2026-01-01', $today),
);

Assert::same(
	PaymentStatus::Overdue,
	PaymentStatus::derive(null, null, '2026-07-07', $today),
);
Assert::same(
	PaymentStatus::Planned,
	PaymentStatus::derive(null, null, '2026-07-08', $today),
);
Assert::same(
	PaymentStatus::Planned,
	PaymentStatus::derive(null, null, '2026-07-09', $today),
);

Assert::same(
	PaymentStatus::Planned,
	PaymentStatus::derive(null, null, '2026-07-07', $today, true),
);
Assert::same(
	PaymentStatus::Planned,
	PaymentStatus::derive(null, null, '2026-01-01', $today, true),
);
Assert::same(
	PaymentStatus::Paid,
	PaymentStatus::derive('2026-07-01', null, '2026-01-01', $today, true),
);
Assert::same(
	PaymentStatus::Skipped,
	PaymentStatus::derive(null, '2026-07-01', '2026-01-01', $today, true),
);
Assert::same(
	PaymentStatus::Overdue,
	PaymentStatus::derive(null, null, '2026-07-07', $today, false),
);

Assert::same('Zaplaceno', PaymentStatus::Paid->label());
Assert::same('Přeskočeno', PaymentStatus::Skipped->label());
Assert::same('Po splatnosti', PaymentStatus::Overdue->label());
Assert::same('Naplánováno', PaymentStatus::Planned->label());
