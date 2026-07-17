<?php

declare(strict_types=1);

namespace App\Payment;

final class YearSummaryResult
{
	/**
	 * @param int $paidThisYear
	 * @param array<int, int> $paidByMonth
	 * @param int $averagePerMonth
	 * @param int $monthlyCommitment
	 * @param int $yearlyCommitmentEstimate
	 * @param int $activeServiceCount
	 * @param list<CategoryBreakdownItem> $categoryBreakdown
	 */
	public function __construct(
		public readonly int $paidThisYear,
		public readonly array $paidByMonth,
		public readonly int $averagePerMonth,
		public readonly int $monthlyCommitment,
		public readonly int $yearlyCommitmentEstimate,
		public readonly int $activeServiceCount,
		public readonly array $categoryBreakdown,
	) {
	}
}
