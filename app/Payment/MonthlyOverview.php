<?php

declare(strict_types=1);

namespace App\Payment;

use App\Support\DueDateCalculator;
use DateTimeImmutable;

/**
 * Čistá agregace dashboardu „Co zaplatit" za jedno období — z aktivních služeb a plateb
 * daného období sestaví položky (existující platba, nebo virtuální naplánováno dopočtené ze
 * šablony), odvodí stav, seřadí, rozdělí do sekcí a spočítá součty. Žádná DB ani „dnes"
 * zevnitř — vše přichází argumentem (viz HomePresenter), takže je celé jednotkově testovatelné.
 */
final class MonthlyOverview
{
	/**
	 * @param list<array<string, mixed>> $services aktivní služby (ServiceRepository::findAll)
	 * @param list<array<string, mixed>> $payments platby daného období (PaymentRepository::findByPeriod)
	 */
	public static function build(
		int $year,
		int $month,
		DateTimeImmutable $today,
		array $services,
		array $payments,
	): OverviewResult {
		/** @var array<int, array<string, mixed>> $paymentsByServiceId */
		$paymentsByServiceId = [];
		foreach ($payments as $payment) {
			$paymentsByServiceId[(int) $payment['service_id']] = $payment;
		}

		$items = [];
		foreach ($services as $service) {
			// Roční služba je kandidát jen ve svém měsíci splatnosti; měsíční vždy.
			if ($service['period'] === 'yearly' && (int) $service['due_month'] !== $month) {
				continue;
			}

			$payment = $paymentsByServiceId[(int) $service['id']] ?? null;
			if ($payment !== null) {
				$dueDate = (string) $payment['due_date'];
				$amount = (int) $payment['amount'];
			} else {
				// Žádný řádek = neřešeno/budoucí — virtuální „naplánováno" s dopočteným
				// due_date a částkou ze šablony služby. Klouzavá služba nemá pevný den
				// splatnosti — řadí se na konec měsíce (poslední den), viz CONTEXT.md.
				$dueDay = (int) ($service['is_sliding'] ?? 0) === 1
					? DueDateCalculator::LastDayOfMonth
					: (int) $service['due_day'];
				$dueDate = DueDateCalculator::calculate($dueDay, $year, $month);
				$amount = (int) $service['amount'];
			}

			$items[] = new DashboardItem(
				$service,
				$dueDate,
				$amount,
				PaymentStatus::derive(
					$payment['paid_date'] ?? null,
					$payment['skipped_at'] ?? null,
					$dueDate,
					$today,
					(int) ($service['is_sliding'] ?? 0) === 1,
				),
			);
		}

		// Řazení: due_date vzestupně, pak id služby (stabilní tie-break v rámci dne).
		usort(
			$items,
			static fn(DashboardItem $a, DashboardItem $b): int
				=> [$a->dueDate, (int) $a->service['id']] <=> [$b->dueDate, (int) $b->service['id']],
		);

		$sections = ['overdue' => [], 'planned' => [], 'paid' => [], 'skipped' => []];
		$remainingTotal = 0;
		$paidTotal = 0;
		foreach ($items as $item) {
			$sectionKey = match ($item->status) {
				PaymentStatus::Overdue => 'overdue',
				PaymentStatus::Planned => 'planned',
				PaymentStatus::Paid => 'paid',
				PaymentStatus::Skipped => 'skipped',
			};
			$sections[$sectionKey][] = $item;

			if ($item->status === PaymentStatus::Paid) {
				$paidTotal += $item->amount;
			} elseif ($item->status !== PaymentStatus::Skipped) {
				$remainingTotal += $item->amount; // po splatnosti + naplánováno = „zbývá zaplatit"
			}
		}

		return new OverviewResult($sections, $remainingTotal, $paidTotal);
	}
}
