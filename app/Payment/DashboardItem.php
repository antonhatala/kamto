<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * Jedna položka dashboardu „Co zaplatit" — služba spojená s (existující nebo virtuálně
 * dopočtenou) platbou za dané období: splatnost, částka a odvozený stav. Value object, aby
 * dashboard/šablona nepracovaly s volnou pěticí polí (Data Clump). Vzniká v MonthlyOverview.
 */
final class DashboardItem
{
	/** @param array<string, mixed> $service řádek `service` (šablona) — pro název/ikonu/id/řazení */
	public function __construct(
		public readonly array $service,
		public readonly string $dueDate,
		public readonly int $amount,
		public readonly PaymentStatus $status,
	) {
	}
}
