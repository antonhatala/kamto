<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Support\Clock;
use DateTimeImmutable;

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
