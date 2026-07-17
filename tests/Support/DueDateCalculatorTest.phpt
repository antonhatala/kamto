<?php

declare(strict_types=1);

use App\Support\DueDateCalculator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::same('2026-04-30', DueDateCalculator::calculate(31, 2026, 4));

Assert::same('2026-02-28', DueDateCalculator::calculate(31, 2026, 2));

Assert::same('2026-02-28', DueDateCalculator::calculate(30, 2026, 2));

Assert::same('2024-02-29', DueDateCalculator::calculate(29, 2024, 2));

Assert::same('2026-02-28', DueDateCalculator::calculate(29, 2026, 2));

Assert::same('2026-06-30', DueDateCalculator::calculate(31, 2026, 6));
Assert::same('2026-09-30', DueDateCalculator::calculate(31, 2026, 9));
Assert::same('2026-11-30', DueDateCalculator::calculate(31, 2026, 11));

Assert::same('2026-02-15', DueDateCalculator::calculate(15, 2026, 2));
Assert::same('2026-04-28', DueDateCalculator::calculate(28, 2026, 4));

Assert::same('2026-12-31', DueDateCalculator::calculate(31, 2026, 12));

Assert::same('2024-02-29', DueDateCalculator::calculate(29, 2024, 2));
Assert::same('2026-02-28', DueDateCalculator::calculate(29, 2026, 2));

Assert::same('2024-02-29', DueDateCalculator::calculate(29, 2024, 2));
Assert::same('2028-02-29', DueDateCalculator::calculate(29, 2028, 2));
Assert::same('2026-02-28', DueDateCalculator::calculate(29, 2026, 2));
Assert::same('2027-02-28', DueDateCalculator::calculate(29, 2027, 2));
Assert::same('2100-02-28', DueDateCalculator::calculate(29, 2100, 2));
