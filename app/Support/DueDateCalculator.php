<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class DueDateCalculator
{
	public const int LastDayOfMonth = 31;

	public static function calculate(int $dueDay, int $year, int $month): string
	{
		$firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
		$daysInMonth = (int) $firstOfMonth->modify('last day of this month')->format('j');
		$day = min($dueDay, $daysInMonth);

		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
}
