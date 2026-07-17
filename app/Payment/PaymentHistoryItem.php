<?php

declare(strict_types=1);

namespace App\Payment;

/** Jeden řádek historie plateb služby (detail služby) — viz ServiceHistory::build. */
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
