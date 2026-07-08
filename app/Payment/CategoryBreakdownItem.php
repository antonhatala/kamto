<?php

declare(strict_types=1);

namespace App\Payment;

/** Jedna položka rozpadu „zaplaceno letos" podle kategorie — viz YearSummary::build. */
final class CategoryBreakdownItem
{
	public function __construct(
		public readonly CategoryDisplay $category,
		public readonly int $amount,
	) {
	}
}
