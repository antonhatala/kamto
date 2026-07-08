<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Jediný zdroj „dnes" pro moduly, které potřebují aktuální čas (dashboard — výchozí období,
 * odvození stavu platby po splatnosti). Seam kvůli testovatelnosti — QA/testy si dosadí
 * vlastní implementaci s pevným datem místo SystemClock (DI, viz config.neon).
 */
interface Clock
{
	public function now(): DateTimeImmutable;
}
