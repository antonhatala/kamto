<?php

declare(strict_types=1);

use App\Support\DueDateCalculator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// E1 — due_day 31 v dubnu (30 dní) -> poslední den měsíce, 30.
Assert::same('2026-04-30', DueDateCalculator::calculate(31, 2026, 4));

// E2 — due_day 31 v únoru 2026 (neprestupný, 28 dní) -> 28.
Assert::same('2026-02-28', DueDateCalculator::calculate(31, 2026, 2));

// E3 — due_day 30 v únoru 2026 -> taky 28 (únor nikdy nemá 30 dní).
Assert::same('2026-02-28', DueDateCalculator::calculate(30, 2026, 2));

// E4 — due_day 29 v únoru 2024 (přestupný) -> 29 beze změny.
Assert::same('2024-02-29', DueDateCalculator::calculate(29, 2024, 2));

// E5 — due_day 29 v únoru 2026 (neprestupný) -> 28.
Assert::same('2026-02-28', DueDateCalculator::calculate(29, 2026, 2));

// E6 — due_day 31 v libovolném 30denním měsíci (duben, červen, září, listopad) -> 30.
Assert::same('2026-06-30', DueDateCalculator::calculate(31, 2026, 6));
Assert::same('2026-09-30', DueDateCalculator::calculate(31, 2026, 9));
Assert::same('2026-11-30', DueDateCalculator::calculate(31, 2026, 11));

// E7 — due_day <= 28 se nikdy neposouvá, bez ohledu na měsíc/rok.
Assert::same('2026-02-15', DueDateCalculator::calculate(15, 2026, 2));
Assert::same('2026-04-28', DueDateCalculator::calculate(28, 2026, 4));

// E8 — roční služba se splatností 31.12. -> 2026-12-31, nepřetéká do ledna dalšího roku.
Assert::same('2026-12-31', DueDateCalculator::calculate(31, 2026, 12));

// E9 — roční služba se splatností 29.2. podle přestupnosti cílového roku.
Assert::same('2024-02-29', DueDateCalculator::calculate(29, 2024, 2)); // přestupný -> 29
Assert::same('2026-02-28', DueDateCalculator::calculate(29, 2026, 2)); // neprestupný -> 28

// E12 — přestupnost přes skutečný gregoriánský kalendář (ne naivní `% 4`): 2024 a 2028 ano,
// 2026, 2027 a "stoletá výjimka" 2100 ne (dělitelné 100, ale ne 400).
Assert::same('2024-02-29', DueDateCalculator::calculate(29, 2024, 2));
Assert::same('2028-02-29', DueDateCalculator::calculate(29, 2028, 2));
Assert::same('2026-02-28', DueDateCalculator::calculate(29, 2026, 2));
Assert::same('2027-02-28', DueDateCalculator::calculate(29, 2027, 2));
Assert::same('2100-02-28', DueDateCalculator::calculate(29, 2100, 2));
