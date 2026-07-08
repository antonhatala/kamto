<?php

declare(strict_types=1);

use App\Support\Money;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// parseCzk — validní vstupy.
Assert::same(129900, Money::parseCzk('1299'));
Assert::same(19950, Money::parseCzk('199,50'));
Assert::same(19950, Money::parseCzk('199.50'));
Assert::same(19950, Money::parseCzk('199,5')); // jedno desetinné místo -> doplní se na haléře.
Assert::same(50, Money::parseCzk('0,5'));
Assert::same(1, Money::parseCzk('0,01'));
Assert::same(100, Money::parseCzk('1'));
Assert::same(129900, Money::parseCzk('  1299  ')); // trim okolního whitespace.
Assert::same(9999999999, Money::parseCzk('99999999,99')); // těsně pod stropem.

// parseCzk — neplatné vstupy.
Assert::null(Money::parseCzk('0')); // musí být > 0.
Assert::null(Money::parseCzk('0,00'));
Assert::null(Money::parseCzk('-5')); // záporné se odmítá.
Assert::null(Money::parseCzk('199,999')); // víc než 2 desetinná místa.
Assert::null(Money::parseCzk('abc'));
Assert::null(Money::parseCzk(''));
Assert::null(Money::parseCzk('1e10'));
Assert::null(Money::parseCzk('100000000')); // strop < 100 000 000 Kč (rovnost už neprojde).
Assert::null(Money::parseCzk('100000000,00'));

// formatCzk — celé koruny bez ",00", tisíce s NBSP, NBSP i před „Kč" (ať se jednotka nezalomí),
// haléře jen když nejsou nulové.
Assert::same("1\u{a0}299\u{a0}Kč", Money::formatCzk(129900));
Assert::same("199,50\u{a0}Kč", Money::formatCzk(19950));
Assert::same("0\u{a0}Kč", Money::formatCzk(0));
Assert::same("1\u{a0}Kč", Money::formatCzk(100));
Assert::same("1\u{a0}000\u{a0}000\u{a0}Kč", Money::formatCzk(100000000));
Assert::same("0,50\u{a0}Kč", Money::formatCzk(50));
Assert::same("0,01\u{a0}Kč", Money::formatCzk(1));

// toInputCzk — hodnota pro předvyplnění inputu: celé koruny bez desetin, jinak s čárkou.
// Bez jednotky i NBSP a v podobě, kterou parseCzk() zase přijme (round-trip).
Assert::same('299', Money::toInputCzk(29900));
Assert::same('199,50', Money::toInputCzk(19950));
Assert::same('0,50', Money::toInputCzk(50));
Assert::same('0,01', Money::toInputCzk(1));
Assert::same('1299', Money::toInputCzk(129900));
Assert::same(29900, Money::parseCzk(Money::toInputCzk(29900)));
Assert::same(19950, Money::parseCzk(Money::toInputCzk(19950)));
