<?php

declare(strict_types=1);

use App\Security\LoginThrottle;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$base = tempnam(sys_get_temp_dir(), 'kamto-throttle-');
$path = $base . '.json';
unlink($base);

$now = 1_000_000;
$throttle = new LoginThrottle($path);

Assert::same(0, $throttle->secondsUntilRetry($now));

for ($i = 0; $i < 4; $i++) {
	$throttle->registerFailure($now);
	Assert::same(0, $throttle->secondsUntilRetry($now));
}

$throttle->registerFailure($now);
Assert::same(2, $throttle->secondsUntilRetry($now));
Assert::same(1, $throttle->secondsUntilRetry($now + 1));
Assert::same(0, $throttle->secondsUntilRetry($now + 2));
Assert::same(0, $throttle->secondsUntilRetry($now + 100));

$throttle->registerFailure($now);
Assert::same(4, $throttle->secondsUntilRetry($now));

for ($i = 0; $i < 7; $i++) {
	$throttle->registerFailure($now);
}
Assert::same(300, $throttle->secondsUntilRetry($now));

$reloaded = new LoginThrottle($path);
Assert::same(300, $reloaded->secondsUntilRetry($now));

$reloaded->registerSuccess();
Assert::same(0, $throttle->secondsUntilRetry($now));
Assert::same(0, $reloaded->secondsUntilRetry($now));

foreach (range(1, 5) as $ignored) {
	(new LoginThrottle($path))->registerFailure($now);
}
Assert::same(2, (new LoginThrottle($path))->secondsUntilRetry($now));

unlink($path);
