<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

final class YearHeatmap
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
	): YearHeatmapResult {
		/** @var array<int, array<string, mixed>> $categoriesById */
		$categoriesById = [];
		foreach ($categories as $category) {
			$categoriesById[(int) $category['id']] = $category;
		}

		/** @var array<int, array<int, array<string, mixed>>> $paymentsByServiceAndMonth */
		$paymentsByServiceAndMonth = [];
		foreach ($payments as $payment) {
			$paymentsByServiceAndMonth[(int) $payment['service_id']][(int) $payment['period_month']] = $payment;
		}

		$rows = [];
		foreach ($services as $service) {
			$serviceId = (int) $service['id'];
			$isArchived = (int) $service['is_archived'] === 1;
			$hasPaymentsInYear = isset($paymentsByServiceAndMonth[$serviceId]);
			$createdYear = (int) substr((string) $service['created_at'], 0, 4);

			if (!$hasPaymentsInYear && ($isArchived || $createdYear > $year)) {
				continue;
			}

			$cells = [];
			for ($month = 1; $month <= 12; $month++) {
				$payment = $paymentsByServiceAndMonth[$serviceId][$month] ?? null;
				$cells[] = PaymentCell::build($service, $year, $month, $payment, $today);
			}

			$categoryId = $service['category_id'] !== null ? (int) $service['category_id'] : null;
			$category = $categoryId !== null ? ($categoriesById[$categoryId] ?? null) : null;

			$rows[] = new HeatmapRow(
				$service,
				$cells,
				CategoryDisplay::resolve($category),
				$service['period'] === 'yearly' ? 'Ročně' : 'Měsíčně',
				$isArchived,
			);
		}

		usort(
			$rows,
			static fn(HeatmapRow $a, HeatmapRow $b): int
				=> [(int) $a->service['is_sliding'], (int) $a->service['due_day'], (int) $a->service['id']]
					<=> [(int) $b->service['is_sliding'], (int) $b->service['due_day'], (int) $b->service['id']],
		);

		return new YearHeatmapResult($rows);
	}
}
