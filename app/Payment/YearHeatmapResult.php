<?php

declare(strict_types=1);

namespace App\Payment;

final class YearHeatmapResult
{
	/** @param list<HeatmapRow> $rows */
	public function __construct(
		public readonly array $rows,
	) {
	}
}
