<?php

declare(strict_types=1);

namespace App\Payment;

final class CategoryBreakdownItem
{
	public function __construct(
		public readonly CategoryDisplay $category,
		public readonly int $amount,
	) {
	}
}
