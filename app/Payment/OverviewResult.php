<?php

declare(strict_types=1);

namespace App\Payment;

final class OverviewResult
{
	/**
	 * @param array{
	 *     overdue: list<DashboardItem>,
	 *     planned: list<DashboardItem>,
	 *     paid: list<DashboardItem>,
	 *     skipped: list<DashboardItem>,
	 * } $sections
	 * @param int $remainingTotal
	 * @param int $paidTotal
	 */
	public function __construct(
		public readonly array $sections,
		public readonly int $remainingTotal,
		public readonly int $paidTotal,
	) {
	}
}
