<?php

declare(strict_types=1);

namespace App\Payment;

final class ServiceHistoryYear
{
	/** @param list<PaymentCell> $cells */
	public function __construct(
		public readonly int $year,
		public readonly array $cells,
	) {
	}
}
