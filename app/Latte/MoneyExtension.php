<?php

declare(strict_types=1);

namespace App\Latte;

use App\Support\Money;
use Latte\Extension;

/** Latte filtr `|czk` — formátování haléřů na čitelnou částku, viz App\Support\Money. */
final class MoneyExtension extends Extension
{
	/** @return array<string, callable> */
	public function getFilters(): array
	{
		return [
			'czk' => Money::formatCzk(...),
		];
	}
}
