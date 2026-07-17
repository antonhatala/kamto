<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
	private const int MaxCzk = 100_000_000;

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

		$haler = (int) str_pad($matches[2] ?? '', 2, '0');
		$amount = $crowns * 100 + $haler;

		return $amount > 0 ? $amount : null;
	}

	public static function formatCzk(int $haler): string
	{
		$crowns = intdiv($haler, 100);
		$rest = $haler % 100;
		$crownsFormatted = number_format($crowns, 0, ',', "\u{a0}");

		return $rest === 0
			? sprintf("%s\u{a0}Kč", $crownsFormatted)
			: sprintf("%s,%02d\u{a0}Kč", $crownsFormatted, $rest);
	}

	public static function toInputCzk(int $haler): string
	{
		$crowns = intdiv($haler, 100);
		$rest = $haler % 100;

		return $rest === 0 ? (string) $crowns : sprintf('%d,%02d', $crowns, $rest);
	}
}
