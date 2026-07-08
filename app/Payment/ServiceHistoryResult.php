<?php

declare(strict_types=1);

namespace App\Payment;

/** Výsledek historie jedné služby — viz ServiceHistory::build. */
final class ServiceHistoryResult
{
	/**
	 * @param list<PaymentHistoryItem> $payments nejnovější nahoře (period_year, period_month desc)
	 * @param int $paidCount kolikrát zaplaceno
	 * @param int $paidTotal Σ amount zaplacených plateb, haléře
	 * @param int $averagePaidAmount paidTotal / paidCount; 0 když nikdy nezaplaceno (žádné dělení nulou)
	 * @param int $skippedCount počet přeskočení
	 * @param array{year: int, month: int}|null $firstPeriod nejstarší období s platbou, nebo null (žádná historie)
	 * @param array{year: int, month: int}|null $lastPeriod nejnovější období s platbou, nebo null
	 * @param list<ServiceHistoryYear> $heatmapYears mini-heatmapa, jeden řádek na rok (vzestupně), přes rozsah let historie
	 */
	public function __construct(
		public readonly array $payments,
		public readonly int $paidCount,
		public readonly int $paidTotal,
		public readonly int $averagePaidAmount,
		public readonly int $skippedCount,
		public readonly ?array $firstPeriod,
		public readonly ?array $lastPeriod,
		public readonly array $heatmapYears,
	) {
	}
}
