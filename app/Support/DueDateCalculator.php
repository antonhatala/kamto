<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Čistá funkce: den splatnosti (`due_day` ze `service`) na konkrétní kalendářní datum pro
 * dané období. Žádná DB, žádné skryté `date()`/"dnes" — rok a měsíc vždy přichází jako
 * argument od volajícího (viz PaymentService::upsert).
 */
final class DueDateCalculator
{
	/**
	 * Den = min(due_day, počet dní v daném měsíci) — u kratších měsíců (duben, únor…) se
	 * den 29–31 posune na poslední den měsíce místo přetečení do dalšího měsíce. Počet dní
	 * v měsíci počítá DateTimeImmutable ("last day of this month") nad skutečným
	 * gregoriánským kalendářem (ne naivní `% 4`), takže respektuje i výjimku století
	 * (2100 není přestupný, 2024/2028 ano) — bez závislosti na ext-calendar (v image není).
	 */
	public static function calculate(int $dueDay, int $year, int $month): string
	{
		$firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
		$daysInMonth = (int) $firstOfMonth->modify('last day of this month')->format('j');
		$day = min($dueDay, $daysInMonth);

		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
}
