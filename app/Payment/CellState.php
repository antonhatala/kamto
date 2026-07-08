<?php

declare(strict_types=1);

namespace App\Payment;

/**
 * Stav jedné buňky heatmapy (rok × měsíc pro jednu službu) — širší než PaymentStatus, protože
 * buňka umí navíc vyjádřit dva stavy, které platba sama o sobě nezná: „mezera" (žádný payment
 * řádek — záměrná pauza, viz CONTEXT.md) a „neaktivní" (měsíc, kdy roční služba vůbec nemá
 * platební období, protože to není její due_month). Viz PaymentCell::build.
 */
enum CellState
{
	case Paid;
	case Skipped;
	case Overdue;
	case Planned;
	case Gap;
	case Inactive;

	/** Převod ze čtyř stavů skutečné platby (PaymentStatus) na odpovídající stav buňky. */
	public static function fromPaymentStatus(PaymentStatus $status): self
	{
		return match ($status) {
			PaymentStatus::Paid => self::Paid,
			PaymentStatus::Skipped => self::Skipped,
			PaymentStatus::Overdue => self::Overdue,
			PaymentStatus::Planned => self::Planned,
		};
	}
}
