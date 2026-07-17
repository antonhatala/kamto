<?php

declare(strict_types=1);

namespace App\Payment;

final class DashboardItem
{
	/** @param array<string, mixed> $service */
	public function __construct(
		public readonly array $service,
		public readonly string $dueDate,
		public readonly int $amount,
		public readonly PaymentStatus $status,
	) {
	}
}
