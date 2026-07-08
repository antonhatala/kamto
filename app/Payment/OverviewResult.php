<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * Výsledek agregace dashboardu za jedno období — položky rozdělené do sekcí + součty.
 * Sekce mají pevné klíče (žebříček stavů); přeskočené jsou mimo oba součty.
 */
final class OverviewResult
{
	/**
	 * @param array{
	 *     overdue: list<DashboardItem>,
	 *     planned: list<DashboardItem>,
	 *     paid: list<DashboardItem>,
	 *     skipped: list<DashboardItem>,
	 * } $sections
	 * @param int $remainingTotal součet nezaplacených (po splatnosti + naplánováno), haléře
	 * @param int $paidTotal součet zaplacených, haléře
	 */
	public function __construct(
		public readonly array $sections,
		public readonly int $remainingTotal,
		public readonly int $paidTotal,
	) {
	}
}
