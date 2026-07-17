<?php

declare(strict_types=1);

namespace App\Payment;

final class PaymentHistoryItem
{
	public function __construct(
		public readonly PaymentStatus $status,
		public readonly int $amount,
		public readonly int $periodYear,
		public readonly int $periodMonth,
		public readonly string $dueDate,
	) {
	}
}
