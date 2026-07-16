<?php

declare(strict_types=1);

namespace App\Payment;

use App\Support\Money;
use App\Support\Months;
use DateTimeImmutable;

/**
 * Čistá agregace řádků CSV exportu historie plateb za rok (Fáze 5) — stejný vzor jako
 * YearSummary/YearHeatmap: žádná DB, "dnes" vždy argumentem, jednotkově testovatelné s
 * fixture poli. Výstup je už hotová `list<list<string>>` pro App\Export\CsvExporter — ten
 * sám o sobě neví nic o platbách/službách, jen skládá CSV string (a řeší injection/BOM/CRLF).
 */
final class PaymentExport
{
	/** Hlavička sloupců (česky) — sdílená mezi buildRows() a voláním CsvExporter::export(). */
	public const array Header = [
		'Služba', 'Kategorie', 'Rok', 'Měsíc', 'Splatnost', 'Zaplaceno', 'Stav', 'Částka (Kč)',
	];

	/**
	 * @param list<array<string, mixed>> $services VŠECHNY služby vč. archivovaných (ServiceRepository::findAll(true))
	 * @param list<array<string, mixed>> $payments platby roku (PaymentRepository::findByYear) — pořadí
	 *     (period_month, service_id) z repozitáře se do řádků přenáší beze změny.
	 * @param list<array<string, mixed>> $categories (CategoryRepository::findAll)
	 * @return list<list<string>> řádky bez hlavičky, po jedné platbě
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
			// Obranná pojistka jako YearSummary/CategoryDisplay — FK CASCADE/SET NULL prakticky
			// vylučuje "osiřelou" platbu/kategorii, ale buildRows() se na to nespoléhá a na
			// chybějící řádek nespadne.
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
