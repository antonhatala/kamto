<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

/** Produkční implementace Clock — skutečné aktuální datum/čas v Europe/Prague (viz CLAUDE.md). */
final class SystemClock implements Clock
{
	public function now(): DateTimeImmutable
	{
		return new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
	}
}
