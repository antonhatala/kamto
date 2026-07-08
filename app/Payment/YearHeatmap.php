<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

/**
 * Čistá agregace roční heatmapy — pro každou relevantní službu 12 buněk (leden–prosinec)
 * s odvozeným stavem plateb. Žádná DB ani „dnes" zevnitř — vše přichází argumentem (viz
 * OverviewPresenter), jednotkově testovatelné (YearHeatmapTest).
 */
final class YearHeatmap
{
	/**
	 * @param list<array<string, mixed>> $services VŠECHNY služby vč. archivovaných (ServiceRepository::findAll(true))
	 * @param list<array<string, mixed>> $payments platby roku (PaymentRepository::findByYear)
	 * @param list<array<string, mixed>> $categories (CategoryRepository::findAll)
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

		// Index plateb [service_id][period_month] — buňky se skládají bez N+1 dotazu.
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
			// created_at je ISO 8601 (date(DATE_ATOM), viz ServiceRepository::insert) — rok jsou
			// vždy první 4 znaky, bez potřeby DateTimeImmutable::createFromFormat.
			$createdYear = (int) substr((string) $service['created_at'], 0, 4);

			// Zobrazit když má aspoň 1 platbu v roce (i archivovaná služba — historická pravda),
			// NEBO je aktivní a existovala už k danému roku. Archivovaná bez plateb v roce a
			// služba vzniklá až po $year se skrývá.
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
				=> [(int) $a->service['sort_order'], (int) $a->service['id']]
					<=> [(int) $b->service['sort_order'], (int) $b->service['id']],
		);

		return new YearHeatmapResult($rows);
	}
}
