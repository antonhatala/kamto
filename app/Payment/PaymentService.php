<?php

declare(strict_types=1);

namespace App\Payment;

use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Support\Clock;
use App\Support\DueDateCalculator;
use InvalidArgumentException;
use RuntimeException;

final class PaymentService
{
	public function __construct(
		private readonly PaymentRepository $paymentRepository,
		private readonly ServiceRepository $serviceRepository,
		private readonly Clock $clock,
	) {
	}

	public function markPaid(int $serviceId, int $year, int $month): void
	{
		$id = $this->upsert($serviceId, $year, $month);
		$this->paymentRepository->setPaidDate($id, $this->clock->now()->format('Y-m-d'));
		$this->paymentRepository->setSkipped($id, null);
	}

	public function unmarkPaid(int $serviceId, int $year, int $month): void
	{
		$id = $this->upsert($serviceId, $year, $month);
		$this->paymentRepository->setPaidDate($id, null);
	}

	public function skip(int $serviceId, int $year, int $month): void
	{
		$id = $this->upsert($serviceId, $year, $month);
		$this->paymentRepository->setSkipped($id, $this->clock->now()->format('Y-m-d'));
		$this->paymentRepository->setPaidDate($id, null);
	}

	public function unskip(int $serviceId, int $year, int $month): void
	{
		$id = $this->upsert($serviceId, $year, $month);
		$this->paymentRepository->setSkipped($id, null);
	}

	public function setAmount(int $serviceId, int $year, int $month, int $amount): void
	{
		$id = $this->upsert($serviceId, $year, $month);
		$this->paymentRepository->setAmount($id, $amount);
	}

	private function upsert(int $serviceId, int $year, int $month): int
	{
		$service = $this->serviceRepository->findActive($serviceId);
		if ($service === null) {
			throw new InvalidArgumentException("Služba {$serviceId} neexistuje nebo je archivovaná.");
		}

		$periodMonth = $service['period'] === 'yearly' ? (int) $service['due_month'] : $month;
		$dueDay = (int) ($service['is_sliding'] ?? 0) === 1
			? DueDateCalculator::LastDayOfMonth
			: (int) $service['due_day'];
		$dueDate = DueDateCalculator::calculate($dueDay, $year, $periodMonth);

		$this->paymentRepository->insertIgnore([
			'service_id' => $serviceId,
			'period_year' => $year,
			'period_month' => $periodMonth,
			'due_date' => $dueDate,
			'amount' => (int) $service['amount'],
		]);

		$row = $this->paymentRepository->findByServiceAndPeriod($serviceId, $year, $periodMonth);
		if ($row === null) {
			throw new RuntimeException("Platbu služby {$serviceId} za {$year}-{$periodMonth} se nepodařilo vytvořit.");
		}

		return (int) $row['id'];
	}
}
