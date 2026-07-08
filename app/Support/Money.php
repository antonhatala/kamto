<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Jediné místo pro parsování/formátování peněz (CZK). Interně vždy integer haléře
 * (CZK×100) — viz CLAUDE.md. Latte filtr `|czk` je zaregistrovaný přes App\Latte\MoneyExtension.
 */
final class Money
{
	/** Strop — částka musí být striktně menší (v Kč). */
	private const int MaxCzk = 100_000_000;

	/**
	 * Rozparsuje uživatelský vstup (čárka i tečka jako des. oddělovač, max 2 des. místa,
	 * kladné číslo, strop < 100 000 000 Kč) na haléře. Vrací null, když vstup není validní.
	 */
	public static function parseCzk(string $input): ?int
	{
		$normalized = trim(str_replace(',', '.', $input));

		if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
			return null;
		}

		$crowns = (int) $matches[1];
		if ($crowns >= self::MaxCzk) {
			return null;
		}

		// str_pad doplní "5" -> "50" (desetiny na haléře), "" -> "00" (celé koruny).
		$haler = (int) str_pad($matches[2] ?? '', 2, '0');
		$amount = $crowns * 100 + $haler;

		return $amount > 0 ? $amount : null;
	}

	/**
	 * Formát pro zobrazení: tisíce oddělené NBSP, desetinná čárka, jednotka „Kč" (oddělená
	 * také NBSP, ať se částka nezalomí od jednotky). Celé koruny bez zbytečných „,00"
	 * (`1 299 Kč`), haléře jen když nejsou nulové (`199,50 Kč`). Předpokládá nezáporné
	 * haléře (doménová invariance — parseCzk() zápornou částku odmítne).
	 */
	public static function formatCzk(int $haler): string
	{
		$crowns = intdiv($haler, 100);
		$rest = $haler % 100;
		$crownsFormatted = number_format($crowns, 0, ',', "\u{a0}");

		return $rest === 0
			? sprintf("%s\u{a0}Kč", $crownsFormatted)
			: sprintf("%s,%02d\u{a0}Kč", $crownsFormatted, $rest);
	}

	/**
	 * Haléře na hodnotu pro předvyplnění inputu formuláře — celé koruny bez desetin
	 * (`299`), jinak koruny s dvoumístnými haléři a desetinnou čárkou (`199,50`). Bez
	 * jednotky i NBSP (do inputu, ne k zobrazení). Zrcadlí formát, který parseCzk() přijme.
	 */
	public static function toInputCzk(int $haler): string
	{
		$crowns = intdiv($haler, 100);
		$rest = $haler % 100;

		return $rest === 0 ? (string) $crowns : sprintf('%d,%02d', $crowns, $rest);
	}
}
