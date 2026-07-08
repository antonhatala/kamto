<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Support\Clock;
use DateTimeImmutable;

/** Clock s pevným „dnes" pro testy — viz App\Support\Clock. */
final class FakeClock implements Clock
{
	public function __construct(
		private readonly DateTimeImmutable $now,
	) {
	}

	public function now(): DateTimeImmutable
	{
		return $this->now;
	}
}
