<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

final class YearSummary
{
	/**
	 * @param list<array<string, mixed>> $services
	 * @param list<array<string, mixed>> $payments
	 * @param list<array<string, mixed>> $categories
	 */
	public static function build(
		int $year,
		DateTimeImmutable $today,
		array $services,
		array $payments,
		array $categories,
	): YearSummaryResult {
		/** @var array<int, array<string, mixed>> $servicesById */
		$servicesById = [];
		foreach ($services as $service) {
			$servicesById[(int) $service['id']] = $service;
		}

		/** @var array<int, array<string, mixed>> $categoriesById */
		$categoriesById = [];
		foreach ($categories as $category) {
			$categoriesById[(int) $category['id']] = $category;
		}

		$paidThisYear = 0;
		$paidByMonth = array_fill(1, 12, 0);
		/** @var array<int, int> $amountByCategoryId */
		$amountByCategoryId = [];
		/** @var array<int, array<string, mixed>|null> $categoryRowById */
		$categoryRowById = [];

		foreach ($payments as $payment) {
			if ($payment['paid_date'] === null) {
				continue;
			}

			$amount = (int) $payment['amount'];
			$paidThisYear += $amount;
			$paidByMonth[(int) $payment['period_month']] += $amount;

			$service = $servicesById[(int) $payment['service_id']] ?? null;
			$categoryId = $service !== null && $service['category_id'] !== null ? (int) $service['category_id'] : 0;

			$amountByCategoryId[$categoryId] = ($amountByCategoryId[$categoryId] ?? 0) + $amount;
			$categoryRowById[$categoryId] = $categoryId !== 0 ? ($categoriesById[$categoryId] ?? null) : null;
		}

		$categoryBreakdown = [];
		foreach ($amountByCategoryId as $categoryId => $amount) {
			$categoryBreakdown[] = new CategoryBreakdownItem(
				CategoryDisplay::resolve($categoryRowById[$categoryId]),
				$amount,
			);
		}
		usort(
			$categoryBreakdown,
			static fn(CategoryBreakdownItem $a, CategoryBreakdownItem $b): int
				=> $b->amount <=> $a->amount ?: $a->category->name <=> $b->category->name,
		);

		$elapsedMonths = self::elapsedMonths($year, $today);
		$averagePerMonth = $elapsedMonths > 0 ? (int) round($paidThisYear / $elapsedMonths) : 0;

		$activeServices = array_filter($services, static fn(array $s): bool => (int) $s['is_archived'] === 0);

		$monthlyCommitment = 0;
		$yearlyServicesSum = 0;
		foreach ($activeServices as $service) {
			if ($service['period'] === 'monthly') {
				$monthlyCommitment += (int) $service['amount'];
			} else {
				$yearlyServicesSum += (int) $service['amount'];
			}
		}

		return new YearSummaryResult(
			$paidThisYear,
			$paidByMonth,
			$averagePerMonth,
			$monthlyCommitment,
			$monthlyCommitment * 12 + $yearlyServicesSum,
			count($activeServices),
			$categoryBreakdown,
		);
	}

	private static function elapsedMonths(int $year, DateTimeImmutable $today): int
	{
		$currentYear = (int) $today->format('Y');

		return match (true) {
			$year < $currentYear => 12,
			$year > $currentYear => 0,
			default => (int) $today->format('n'),
		};
	}
}
