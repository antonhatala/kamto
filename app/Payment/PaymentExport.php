<?php

declare(strict_types=1);

namespace App\Payment;

use App\Support\Money;
use App\Support\Months;
use DateTimeImmutable;

final class PaymentExport
{
	public const array Header = [
		'Služba', 'Kategorie', 'Rok', 'Měsíc', 'Splatnost', 'Zaplaceno', 'Stav', 'Částka (Kč)',
	];

	/**
	 * @param list<array<string, mixed>> $services
	 * @param list<array<string, mixed>> $payments
	 * @param list<array<string, mixed>> $categories
	 * @return list<list<string>>
	 */
	public static function buildRows(
		DateTimeImmutable $today,
		array $services,
		array $payments,
		array $categories,
	): array {
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

		$rows = [];
		foreach ($payments as $payment) {
			$service = $servicesById[(int) $payment['service_id']] ?? null;
			$categoryRow = null;
			if ($service !== null && $service['category_id'] !== null) {
				$categoryRow = $categoriesById[(int) $service['category_id']] ?? null;
			}
			$category = CategoryDisplay::resolve($categoryRow);

			$paidDate = $payment['paid_date'] !== null ? (string) $payment['paid_date'] : null;
			$skippedAt = $payment['skipped_at'] !== null ? (string) $payment['skipped_at'] : null;
			$isSliding = $service !== null && (int) ($service['is_sliding'] ?? 0) === 1;
			$status = PaymentStatus::derive($paidDate, $skippedAt, (string) $payment['due_date'], $today, $isSliding);

			$rows[] = [
				$service !== null ? (string) $service['name'] : '',
				$category->name,
				(string) $payment['period_year'],
				Months::Names[(int) $payment['period_month']],
				(string) $payment['due_date'],
				$paidDate ?? '',
				$status->label(),
				Money::toInputCzk((int) $payment['amount']),
			];
		}

		return $rows;
	}
}
