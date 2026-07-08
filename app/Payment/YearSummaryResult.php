<?php

declare(strict_types=1);

namespace App\Payment;

/** Výsledek roční agregace — viz YearSummary::build. */
final class YearSummaryResult
{
	/**
	 * @param int $paidThisYear Σ amount plateb roku se stavem zaplaceno (vč. archivovaných služeb), haléře
	 * @param array<int, int> $paidByMonth zaplaceno rozpadené dle period_month platby (index 1–12, haléře);
	 *     Σ paidByMonth === paidThisYear vždy (počítáno z plateb v téže smyčce, ne z buněk heatmapy)
	 * @param int $averagePerMonth zaplaceno / uplynulé měsíce roku; 0 když nelze dělit (budoucí rok)
	 * @param int $monthlyCommitment Σ amount aktivních měsíčních služeb, haléře
	 * @param int $yearlyCommitmentEstimate odhad ročního závazku = monthlyCommitment × 12 + Σ aktivních ročních služeb
	 * @param int $activeServiceCount počet nearchivovaných služeb
	 * @param list<CategoryBreakdownItem> $categoryBreakdown seřazeno sestupně dle částky
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
