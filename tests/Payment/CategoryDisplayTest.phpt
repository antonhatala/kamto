<?php

declare(strict_types=1);

use App\Payment\CategoryDisplay;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

const Fallback = '#a8a29e';

$none = CategoryDisplay::resolve(null);
Assert::same('Bez kategorie', $none->name);
Assert::same(Fallback, $none->color);

$valid = CategoryDisplay::resolve(['name' => 'Bydlení', 'color' => '#c1622e']);
Assert::same('Bydlení', $valid->name);
Assert::same('#c1622e', $valid->color);

$upper = CategoryDisplay::resolve(['name' => 'X', 'color' => '#AABBCC']);
Assert::same('#AABBCC', $upper->color);

foreach ([
	'red',
	'#abc',
	'#12345',
	'#1234567',
	'#12g456',
	'#c1622e; background: url(x)',
	'',
	'c1622e',
] as $bad) {
	$result = CategoryDisplay::resolve(['name' => 'Podezřelá', 'color' => $bad]);
	Assert::same('Podezřelá', $result->name);
	Assert::same(Fallback, $result->color, "color '{$bad}' měl spadnout na fallback");
}
