<?php

declare(strict_types=1);

use App\Payment\PaymentStatus;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$today = new DateTimeImmutable('2026-07-08');

// Žebříček: zaplaceno má přednost před vším ostatním, i kdyby byl skipped_at taky vyplněný
// (nemělo by nastat současně, ale derive() je čistá funkce a nemá to ověřovat/vynucovat).
Assert::same(
	PaymentStatus::Paid,
	PaymentStatus::derive('2026-07-01', '2026-07-01', '2026-07-05', $today),
);
Assert::same(
	PaymentStatus::Paid,
	PaymentStatus::derive('2026-07-01', null, '2026-01-01', $today),
);

// Přeskočeno — paid_date NULL, skipped_at vyplněný, bez ohledu na due_date.
Assert::same(
	PaymentStatus::Skipped,
	PaymentStatus::derive(null, '2026-07-01', '2026-01-01', $today),
);

// E14 — po splatnosti jen když due_date < dnes STRIKTNĚ; přesně v den splatnosti je
// platba ještě naplánovaná (ne po splatnosti).
Assert::same(
	PaymentStatus::Overdue,
	PaymentStatus::derive(null, null, '2026-07-07', $today), // due_date včera
);
Assert::same(
	PaymentStatus::Planned,
	PaymentStatus::derive(null, null, '2026-07-08', $today), // due_date == dnes
);
Assert::same(
	PaymentStatus::Planned,
	PaymentStatus::derive(null, null, '2026-07-09', $today), // due_date zítra
);

// label() — český popisek pro dashboard.
Assert::same('Zaplaceno', PaymentStatus::Paid->label());
Assert::same('Přeskočeno', PaymentStatus::Skipped->label());
Assert::same('Po splatnosti', PaymentStatus::Overdue->label());
Assert::same('Naplánováno', PaymentStatus::Planned->label());
