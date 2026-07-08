<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Platný rozsah roku pro navigaci mezi obdobími (dashboard i roční přehledy) — sdíleno
 * HomePresenter a OverviewPresenter, ať se limit nekopíruje na dvou místech.
 */
final class YearRange
{
	public const int Min = 2000;
	public const int Max = 2100;

	public static function isValid(int $year): bool
	{
		return $year >= self::Min && $year <= self::Max;
	}
}
