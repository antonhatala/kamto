<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sdílené české názvy měsíců (1–12) — používá ServicePresenter (select měsíce splatnosti
 * roční služby) i HomePresenter (dashboard). Jediné místo, ať se nekopíruje.
 */
final class Months
{
	public const array Names = [
		1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben', 5 => 'Květen', 6 => 'Červen',
		7 => 'Červenec', 8 => 'Srpen', 9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
	];
}
