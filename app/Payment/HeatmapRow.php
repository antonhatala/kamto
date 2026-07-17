<?php

declare(strict_types=1);

namespace App\Payment;

final class HeatmapRow
{
	/**
	 * @param array<string, mixed> $service
	 * @param list<PaymentCell> $cells
	 */
	public function __construct(
		public readonly array $service,
		public readonly array $cells,
		public readonly CategoryDisplay $category,
		public readonly string $periodBadge,
		public readonly bool $isArchived,
	) {
	}
}
