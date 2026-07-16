<?php

declare(strict_types=1);

namespace App\Payment;

use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Support\Clock;
use App\Support\DueDateCalculator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tenká vrstva nad PaymentRepository — lazy upsert řádku platby (vzniká až první akcí,
 * viz CONTEXT.md) a přechody mezi stavy (zaplaceno/přeskočeno/částka). Volající zná jen
 * (serviceId, year, month) — kterou konkrétní payment řádek to je (existuje/vznikne),
 * jaké je jeho due_date a snapshot částky, řeší tento modul.
 */
final class PaymentService
{
	public function __construct(
		private readonly PaymentRepository $paymentRepository,
		private readonly ServiceRepository $serviceRepository,
		private readonly Clock $clock,
	) {
	}

	// Zaplaceno a přeskočeno jsou vzájemně výlučné stavy (žebříček v CONTEXT.md) — pozitivní
	// přechod proto zároveň nuluje opačný příznak, aby řádek nikdy nedržel oba naráz.

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

	/**
	 * Ruční úprava částky pro dané období — mění jen tento jeden payment řádek, ne šablonu
	 * (service.amount). Pozdější změna service.amount proto existující řádky nepřepočítá.
	 */
	public function setAmount(int $serviceId, int $year, int $month, int $amount, ?string $note): void
	{
		$id = $this->upsert($serviceId, $year, $month);
		$this->paymentRepository->setAmount($id, $amount, $note);
	}

	/**
	 * Najde existující řádek platby pro (serviceId, year, month), nebo ho založí — se
	 * snapshotem aktuální service.amount a dopočteným due_date (DueDateCalculator). Vrátí id
	 * řádku (existujícího i čerstvě vzniklého).
	 *
	 * Idempotentní i při souběhu: insertIgnore() při kolizi na UNIQUE nic neudělá a existující
	 * řádek se pak dohledá — dva paralelní přechody nad stejným čerstvým obdobím nikdy
	 * neshodí 500 (viz PaymentRepository::insertIgnore). Existující řádek se nikdy nepřepíše,
	 * takže snapshot částky/due_date zůstává.
	 *
	 * Platí jen pro aktivní službu — archivovaná do platebního flow nesmí (crafted signál →
	 * volající to promítne na 404). Roční služba se eviduje vždy na svůj měsíc splatnosti
	 * (due_month), ne na předaný $month.
	 */
	private function upsert(int $serviceId, int $year, int $month): int
	{
		$service = $this->serviceRepository->findActive($serviceId);
		if ($service === null) {
			throw new InvalidArgumentException("Služba {$serviceId} neexistuje nebo je archivovaná.");
		}

		$periodMonth = $service['period'] === 'yearly' ? (int) $service['due_month'] : $month;
		// Klouzavá služba (viz CONTEXT.md) nemá pevný den splatnosti — due_date se dopočte na
		// poslední den daného měsíce (jen pro řazení "na konec měsíce", k Overdue se u klouzavé
		// stejně nepoužije, viz PaymentStatus::derive).
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
			// Po insertIgnore řádek vždy existuje — obranná pojistka (nemělo by nastat).
			throw new RuntimeException("Platbu služby {$serviceId} za {$year}-{$periodMonth} se nepodařilo vytvořit.");
		}

		return (int) $row['id'];
	}
}
