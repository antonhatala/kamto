<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

enum PaymentStatus
{
	case Paid;
	case Skipped;
	case Overdue;
	case Planned;

	public static function derive(
		?string $paidDate,
		?string $skippedAt,
		string $dueDate,
		DateTimeImmutable $today,
		bool $isSliding = false,
	): self {
		return match (true) {
			$paidDate !== null => self::Paid,
			$skippedAt !== null => self::Skipped,
			!$isSliding && $dueDate < $today->format('Y-m-d') => self::Overdue,
			default => self::Planned,
		};
	}

	public function label(): string
	{
		return match ($this) {
			self::Paid => 'Zaplaceno',
			self::Skipped => 'Přeskočeno',
			self::Overdue => 'Po splatnosti',
			self::Planned => 'Naplánováno',
		};
	}
}
