<?php

declare(strict_types=1);

use App\Payment\CategoryDisplay;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

const Fallback = '#a8a29e';

// NULL kategorie (služba bez kategorie) -> neutrální fallback.
$none = CategoryDisplay::resolve(null);
Assert::same('Bez kategorie', $none->name);
Assert::same(Fallback, $none->color);

// Platný 6místný hex projde beze změny (whitelist z CategoryPresenter::Palette).
$valid = CategoryDisplay::resolve(['name' => 'Bydlení', 'color' => '#c1622e']);
Assert::same('Bydlení', $valid->name);
Assert::same('#c1622e', $valid->color);

// Velká písmena v hexu jsou taky platná (case-insensitive).
$upper = CategoryDisplay::resolve(['name' => 'X', 'color' => '#AABBCC']);
Assert::same('#AABBCC', $upper->color);

// Hardening (security info) — cokoli mimo tvar ^#[0-9a-f]{6}$ spadne na fallback, aby |noescape
// ve style atributu nezáviselo na integritě zápisu do DB. Jméno zůstává (escapuje ho Latte běžně).
foreach ([
	'red',                              // pojmenovaná barva, ne hex
	'#abc',                             // 3místný hex (whitelist ho nepoužívá)
	'#12345',                           // 5 znaků
	'#1234567',                         // 7 znaků
	'#12g456',                          // nehexadecimální znak
	'#c1622e; background: url(x)',      // pokus o injekci do style atributu
	'',                                 // prázdné
	'c1622e',                           // bez #
] as $bad) {
	$result = CategoryDisplay::resolve(['name' => 'Podezřelá', 'color' => $bad]);
	Assert::same('Podezřelá', $result->name);
	Assert::same(Fallback, $result->color, "color '{$bad}' měl spadnout na fallback");
}
