<?php

declare(strict_types=1);

namespace App\Payment;

final class ServiceHistoryResult
{
	/**
	 * @param list<PaymentHistoryItem> $payments
	 * @param int $paidCount
	 * @param int $paidTotal
	 * @param int $averagePaidAmount
	 * @param int $skippedCount
	 * @param array{year: int, month: int}|null $firstPeriod
	 * @param array{year: int, month: int}|null $lastPeriod
	 * @param list<ServiceHistoryYear> $heatmapYears
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
