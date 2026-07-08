<?php

declare(strict_types=1);

use App\Support\YearRange;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::true(YearRange::isValid(2000));
Assert::true(YearRange::isValid(2100));
Assert::true(YearRange::isValid(2026));
Assert::false(YearRange::isValid(1999));
Assert::false(YearRange::isValid(2101));
