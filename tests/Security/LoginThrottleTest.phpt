<?php

declare(strict_types=1);

use App\Security\LoginThrottle;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$base = tempnam(sys_get_temp_dir(), 'kamto-throttle-');
$path = $base . '.json';
unlink($base); // Smaže soubor, který tempnam() skutečně vytvořil — LoginThrottle vytvoří vlastní ($path) při prvním zápisu.

$now = 1_000_000;
$throttle = new LoginThrottle($path);

// Bez neúspěchů lze zkusit hned.
Assert::same(0, $throttle->secondsUntilRetry($now));

// Prvních 5 neúspěchů ještě nezpomaluje (MaxAttemptsBeforeDelay).
for ($i = 0; $i < 4; $i++) {
	$throttle->registerFailure($now);
	Assert::same(0, $throttle->secondsUntilRetry($now));
}

// 5. neúspěch spouští zpoždění (base 2 s).
$throttle->registerFailure($now);
Assert::same(2, $throttle->secondsUntilRetry($now));
Assert::same(1, $throttle->secondsUntilRetry($now + 1));
Assert::same(0, $throttle->secondsUntilRetry($now + 2));
Assert::same(0, $throttle->secondsUntilRetry($now + 100));

// 6. neúspěch — zpoždění roste exponenciálně (4 s).
$throttle->registerFailure($now);
Assert::same(4, $throttle->secondsUntilRetry($now));

// Dalších 7 neúspěchů (celkem 13) — zpoždění se zastaví na stropu (5 min).
for ($i = 0; $i < 7; $i++) {
	$throttle->registerFailure($now);
}
Assert::same(300, $throttle->secondsUntilRetry($now));

// Nový objekt nad stejným souborem vidí stejný (persistentní) stav.
$reloaded = new LoginThrottle($path);
Assert::same(300, $reloaded->secondsUntilRetry($now));

// Úspěšné přihlášení čítač vynuluje.
$reloaded->registerSuccess();
Assert::same(0, $throttle->secondsUntilRetry($now));
Assert::same(0, $reloaded->secondsUntilRetry($now));

// Atomicita read-modify-write: každý neúspěch přes ČERSTVOU instanci musí vidět předchozí
// zápis a inkrement neztratit. 5 neúspěchů → 5. spustí zpoždění (2 s); kdyby se update ztratil,
// čítač by nedošel na 5 a zpoždění by bylo 0.
foreach (range(1, 5) as $ignored) {
	(new LoginThrottle($path))->registerFailure($now);
}
Assert::same(2, (new LoginThrottle($path))->secondsUntilRetry($now));

unlink($path);
