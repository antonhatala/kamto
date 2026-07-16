<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

/**
 * Jedna buňka heatmapy — stav jedné služby v jednom kalendářním měsíci. Sdílené jádro mezi
 * YearHeatmap (řádek = služba, 12 buněk napříč rokem) a ServiceHistory (mini-heatmapa jedné
 * služby napříč všemi lety historie) — ať se „řeč buňky" nekopíruje na dvou místech.
 *
 * Na rozdíl od MonthlyOverview (dashband „Co zaplatit") tady chybějící payment řádek NIKDY
 * nedopočítává virtuální naplánovanou položku — je to vždy mezera/pauza (CONTEXT.md: „Pauza /
 * mezera" je záměrný signál, ne chyba dat). Heatmapa se dívá zpět do historie, ne dopředu na
 * co je třeba udělat.
 */
final class PaymentCell
{
	private function __construct(
		public readonly CellState $state,
		public readonly int $periodYear,
		public readonly int $periodMonth,
		/** Haléře, nebo null když se buňka na částku neptá (Gap/Inactive). */
		public readonly ?int $amount,
	) {
	}

	/**
	 * @param array<string, mixed> $service řádek `service` — čte se jen period/due_month/is_sliding
	 * @param array<string, mixed>|null $payment řádek `payment` pro (service, $year, $month), nebo null
	 */
	public static function build(
		array $service,
		int $year,
		int $month,
		?array $payment,
		DateTimeImmutable $today,
	): self {
		// Payment řádek je ground truth: když pro (service, year, month) existuje, VŽDY z něj
		// odvoď stav — bez ohledu na aktuální periodu/due_month služby. Editace periody nebo
		// due_month (formulář to nehlídá) může "osiřet" dřív zaplacenou roční platbu do měsíce,
		// který už není due_month; ta musí zůstat viditelná (jinak zmizí z heatmapy jako
		// Inactive, ale v seznamu plateb detailu je → nesoulad uvnitř stránky, QA#1).
		if ($payment !== null) {
			$status = PaymentStatus::derive(
				$payment['paid_date'] ?? null,
				$payment['skipped_at'] ?? null,
				(string) $payment['due_date'],
				$today,
				(int) ($service['is_sliding'] ?? 0) === 1,
			);

			return new self(
				CellState::fromPaymentStatus($status),
				$year,
				$month,
				(int) $payment['amount'],
			);
		}

		// Žádný payment řádek. Roční služba má platební období jen ve svém due_month — ostatních
		// 11 měsíců pro ni jako koncept vůbec neexistuje (Inactive, odlišné od "mezera"). Jinak
		// (měsíční, nebo roční ve svém due_month) je to klidná pauza (Gap).
		if ($service['period'] === 'yearly' && (int) $service['due_month'] !== $month) {
			return new self(CellState::Inactive, $year, $month, null);
		}

		return new self(CellState::Gap, $year, $month, null);
	}
}
