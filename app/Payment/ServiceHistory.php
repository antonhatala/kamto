<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

/**
 * Čistá agregace historie jedné služby (detail služby, Fáze 4) — seznam plateb nejnovější
 * nahoře, součty (kolikrát zaplaceno, celkem, průměr, přeskočení, první/poslední období) a
 * data pro mini-heatmapu napříč všemi lety historie. Žádná DB ani „dnes" zevnitř — vše
 * přichází argumentem (viz ServicePresenter), jednotkově testovatelné (ServiceHistoryTest).
 *
 * Buňková logika (Paid/Skipped/Overdue/Planned/Gap/Inactive) je sdílená s YearHeatmap přes
 * PaymentCell — ať se nekopíruje na dvou místech.
 */
final class ServiceHistory
{
	/**
	 * @param array<string, mixed> $service řádek `service`
	 * @param list<array<string, mixed>> $payments platby služby, ASC dle period_year, period_month (PaymentRepository::findByService)
	 */
	public static function build(DateTimeImmutable $today, array $service, array $payments): ServiceHistoryResult
	{
		$items = [];
		$paidCount = 0;
		$paidTotal = 0;
		$skippedCount = 0;

		foreach ($payments as $payment) {
			$dueDate = (string) $payment['due_date'];
			$status = PaymentStatus::derive(
				$payment['paid_date'] ?? null,
				$payment['skipped_at'] ?? null,
				$dueDate,
				$today,
				(int) ($service['is_sliding'] ?? 0) === 1,
			);

			$amount = (int) $payment['amount'];
			$items[] = new PaymentHistoryItem(
				$status,
				$amount,
				$payment['note'] !== null ? (string) $payment['note'] : null,
				(int) $payment['period_year'],
				(int) $payment['period_month'],
				$dueDate,
			);

			if ($status === PaymentStatus::Paid) {
				$paidCount++;
				$paidTotal += $amount;
			} elseif ($status === PaymentStatus::Skipped) {
				$skippedCount++;
			}
		}

		$firstPeriod = null;
		$lastPeriod = null;
		if ($payments !== []) {
			// Vstup přichází ASC (period_year, period_month) — první/poslední prvek stačí,
			// není potřeba samostatné porovnávání min/max.
			$firstPeriod = ['year' => (int) $payments[0]['period_year'], 'month' => (int) $payments[0]['period_month']];
			$last = $payments[count($payments) - 1];
			$lastPeriod = ['year' => (int) $last['period_year'], 'month' => (int) $last['period_month']];
		}

		$averagePaidAmount = $paidCount > 0 ? (int) round($paidTotal / $paidCount) : 0;

		return new ServiceHistoryResult(
			array_reverse($items), // Nejnovější nahoře pro zobrazení.
			$paidCount,
			$paidTotal,
			$averagePaidAmount,
			$skippedCount,
			$firstPeriod,
			$lastPeriod,
			self::buildHeatmapYears($today, $service, $payments),
		);
	}

	/**
	 * @param array<string, mixed> $service
	 * @param list<array<string, mixed>> $payments ASC dle period_year, period_month
	 * @return list<ServiceHistoryYear>
	 */
	private static function buildHeatmapYears(DateTimeImmutable $today, array $service, array $payments): array
	{
		if ($payments === []) {
			return []; // Žádná platba -> žádná historie k zobrazení.
		}

		/** @var array<int, array<int, array<string, mixed>>> $paymentsByYearAndMonth */
		$paymentsByYearAndMonth = [];
		$years = [];
		foreach ($payments as $payment) {
			$year = (int) $payment['period_year'];
			$paymentsByYearAndMonth[$year][(int) $payment['period_month']] = $payment;
			$years[] = $year;
		}

		$heatmapYears = [];
		for ($year = min($years); $year <= max($years); $year++) {
			$cells = [];
			for ($month = 1; $month <= 12; $month++) {
				$payment = $paymentsByYearAndMonth[$year][$month] ?? null;
				$cells[] = PaymentCell::build($service, $year, $month, $payment, $today);
			}
			$heatmapYears[] = new ServiceHistoryYear($year, $cells);
		}

		return $heatmapYears;
	}
}
