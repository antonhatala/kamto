<?php

declare(strict_types=1);

namespace App\Payment;

use DateTimeImmutable;

final class PaymentCell
{
	private function __construct(
		public readonly CellState $state,
		public readonly int $periodYear,
		public readonly int $periodMonth,
		public readonly ?int $amount,
	) {
	}

	/**
	 * @param array<string, mixed> $service
	 * @param array<string, mixed>|null $payment
	 */
	public static function build(
		array $service,
		int $year,
		int $month,
		?array $payment,
		DateTimeImmutable $today,
	): self {
		if ($payment !== null) {
			$status = PaymentStatus::derive(
				$payment['paid_date'] ?? null,
				$payment['skipped_at'] ?? null,
				(string) $payment['due_date'],
				$today,
				(int) ($service['is_sliding'] ?? 0) === 1,
			);

			return new self(
				CellState::fromPaymentStatus($status),
				$year,
				$month,
				(int) $payment['amount'],
			);
		}

		if ($service['period'] === 'yearly' && (int) $service['due_month'] !== $month) {
			return new self(CellState::Inactive, $year, $month, null);
		}

		return new self(CellState::Gap, $year, $month, null);
	}
}
