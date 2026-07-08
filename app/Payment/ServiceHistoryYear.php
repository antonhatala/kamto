<?php

declare(strict_types=1);

namespace App\Payment;

/** Jeden rok mini-heatmapy v detailu služby — 12 buněk. Viz ServiceHistory::build. */
final class ServiceHistoryYear
{
	/** @param list<PaymentCell> $cells přesně 12 buněk, index 0 = leden … index 11 = prosinec */
	public function __construct(
		public readonly int $year,
		public readonly array $cells,
	) {
	}
}
