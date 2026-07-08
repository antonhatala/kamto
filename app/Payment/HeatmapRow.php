<?php

declare(strict_types=1);

namespace App\Payment;

/** Jeden řádek roční heatmapy — jedna služba, 12 buněk (leden–prosinec). Viz YearHeatmap::build. */
final class HeatmapRow
{
	/**
	 * @param array<string, mixed> $service řádek `service` — pro název/ikonu/id
	 * @param list<PaymentCell> $cells přesně 12 buněk, index 0 = leden … index 11 = prosinec
	 */
	public function __construct(
		public readonly array $service,
		public readonly array $cells,
		public readonly CategoryDisplay $category,
		/** Český popisek periody služby — "Ročně"/"Měsíčně". */
		public readonly string $periodBadge,
		public readonly bool $isArchived,
	) {
	}
}
