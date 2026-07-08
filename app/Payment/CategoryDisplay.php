<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * Jméno + barva kategorie pro zobrazení, s jednotným neutrálním fallbackem pro službu bez
 * kategorie (category_id NULL) i pro dřív přiřazenou kategorii, která mezitím zmizela (FK
 * `ON DELETE SET NULL` to sice prakticky vylučuje, ale resolve() se nespoléhá na cizí
 * invarianty a nikdy nespadne na chybějícím hexu). Sdíleno mezi YearSummary (rozpad podle
 * kategorie) a YearHeatmap (barva řádku), ať se fallback nedefinuje na dvou místech.
 */
final class CategoryDisplay
{
	/** Mimo serverový whitelist barev (CategoryPresenter::Palette) — neutrální stone tón,
	 * ne kategorie, kterou by šlo v UI vybrat. */
	private const string FallbackColor = '#a8a29e';
	private const string FallbackName = 'Bez kategorie';

	private function __construct(
		public readonly string $name,
		public readonly string $color,
	) {
	}

	/** @param array<string, mixed>|null $category řádek `category`, nebo null */
	public static function resolve(?array $category): self
	{
		if ($category === null) {
			return new self(self::FallbackName, self::FallbackColor);
		}

		// Barva se v šabloně vypisuje přes |noescape (Latte by "#" ve style atributu escapoval).
		// Re-validace na 6místný hex (defense-in-depth) — |noescape tak nezávisí na integritě
		// zápisu do DB; cokoli mimo tvar spadne na neutrální fallback místo do CSS/atributu.
		$color = (string) $category['color'];
		if (preg_match('/^#[0-9a-f]{6}$/i', $color) !== 1) {
			$color = self::FallbackColor;
		}

		return new self((string) $category['name'], $color);
	}
}
