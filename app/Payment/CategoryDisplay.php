<?php

declare(strict_types=1);

namespace App\Payment;

final class CategoryDisplay
{
	private const string FallbackColor = '#a8a29e';
	private const string FallbackName = 'Bez kategorie';

	private function __construct(
		public readonly string $name,
		public readonly string $color,
	) {
	}

	/** @param array<string, mixed>|null $category */
	public static function resolve(?array $category): self
	{
		if ($category === null) {
			return new self(self::FallbackName, self::FallbackColor);
		}

		$color = (string) $category['color'];
		if (preg_match('/^#[0-9a-f]{6}$/i', $color) !== 1) {
			$color = self::FallbackColor;
		}

		return new self((string) $category['name'], $color);
	}
}
