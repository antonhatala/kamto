<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

/**
 * Odvozený stav platby (žádný status sloupec v DB, viz CONTEXT.md) — žebříček z
 * (paid_date, skipped_at, due_date, dnes). „Žádný řádek" (budoucí/neřešené období) není
 * hodnota tohoto enumu — dashboard takový případ řeší jako virtuální Planned, viz HomePresenter.
 */
enum PaymentStatus
{
	case Paid;
	case Skipped;
	case Overdue;
	case Planned;

	/**
	 * Čistá funkce — žádná DB, „dnes" vždy z argumentu (Clock na úrovni volajícího, viz
	 * App\Support\Clock), ať je testovatelná s libovolným pevným datem.
	 *
	 * Žebříček: zaplaceno > přeskočeno > po splatnosti (due_date < dnes, striktně — v den
	 * splatnosti je platba ještě naplánovaná) > naplánováno.
	 *
	 * Klouzavá služba (`service.is_sliding`, viz CONTEXT.md) nemá pevný den splatnosti —
	 * větev "po splatnosti" se pro ni přeskakuje, nezaplacená/nepřeskočená platba tak vždy
	 * zůstává *naplánováno*, i když je due_date v minulosti.
	 */
	public static function derive(
		?string $paidDate,
		?string $skippedAt,
		string $dueDate,
		DateTimeImmutable $today,
		bool $isSliding = false,
	): self {
		return match (true) {
			$paidDate !== null => self::Paid,
			$skippedAt !== null => self::Skipped,
			!$isSliding && $dueDate < $today->format('Y-m-d') => self::Overdue,
			default => self::Planned,
		};
	}

	/** Český popisek stavu pro zobrazení — enum je zdroj názvů (dashboard, přehledy Fáze 4). */
	public function label(): string
	{
		return match ($this) {
			self::Paid => 'Zaplaceno',
			self::Skipped => 'Přeskočeno',
			self::Overdue => 'Po splatnosti',
			self::Planned => 'Naplánováno',
		};
	}
}
