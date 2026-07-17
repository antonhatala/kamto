<?php

declare(strict_types=1);

namespace App\Payment;

enum CellState
{
	case Paid;
	case Skipped;
	case Overdue;
	case Planned;
	case Gap;
	case Inactive;

	public static function fromPaymentStatus(PaymentStatus $status): self
	{
		return match ($status) {
			PaymentStatus::Paid => self::Paid,
			PaymentStatus::Skipped => self::Skipped,
			PaymentStatus::Overdue => self::Overdue,
			PaymentStatus::Planned => self::Planned,
		};
	}
}
