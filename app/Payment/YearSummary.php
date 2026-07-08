<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

/**
 * Čistá agregace ročního souhrnu — kolik je letos zaplaceno (napříč všemi službami vč.
 * archivovaných, historická pravda), měsíční průměr, aktuální měsíční/roční závazek (jen
 * aktivní služby) a rozpad zaplaceného podle kategorie. Žádná DB ani „dnes" zevnitř — vše
 * přichází argumentem (viz OverviewPresenter), jednotkově testovatelné (YearSummaryTest).
 */
final class YearSummary
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
		// Rozpad zaplaceného po měsících pro roční graf — počítá se z PLATEB (period_month
		// platby) v téže smyčce co paidThisYear, takže Σ paidByMonth === paidThisYear vždy.
		// (Dřív to dopočítávala šablona z Paid buněk heatmapy → roční platba mimo due_month je
		// buňka Inactive/amount=null a z grafu vypadla, ale v headline byla → nesoulad.)
		$paidByMonth = array_fill(1, 12, 0);
		// Klíč 0 = "bez kategorie" (žádné reálné id kategorie nikdy 0, SQLite INTEGER PRIMARY
		// KEY začíná na 1) — jednoduché indexování bez mixed int|string klíčů.
		/** @var array<int, int> $amountByCategoryId */
		$amountByCategoryId = [];
		/** @var array<int, array<string, mixed>|null> $categoryRowById */
		$categoryRowById = [];

		foreach ($payments as $payment) {
			if ($payment['paid_date'] === null) {
				continue; // Jen zaplacené platby patří do "letos zaplaceno" (žebříček stavu, CONTEXT.md).
			}

			$amount = (int) $payment['amount'];
			$paidThisYear += $amount;
			// period_month platby (u roční služby její due_month) — DB CHECK garantuje 1–12.
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
		// Sestupně dle částky; shodná částka -> abecedně dle jména (deterministické pořadí).
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

	/**
	 * Kolik měsíců roku už uplynulo vzhledem k "dnes" — minulý rok vždy celých 12, budoucí
	 * rok 0 (žádný uplynulý měsíc, volající se pak dělení nulou nesmí ani pokusit), aktuální
	 * rok číslo měsíce dle "dnes".
	 */
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
