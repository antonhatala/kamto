<?php

declare(strict_types=1);

namespace App\Payment;

/** Výsledek roční heatmapy — řádky seřazené is_sliding, due_day, id. Viz YearHeatmap::build. */
final class YearHeatmapResult
{
	/** @param list<HeatmapRow> $rows */
	public function __construct(
		public readonly array $rows,
	) {
	}
}
