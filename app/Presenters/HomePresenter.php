<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Payment\MonthlyOverview;
use App\Payment\PaymentService;
use App\Support\Clock;
use App\Support\Money;
use App\Support\Months;
use App\Support\YearRange;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Forms\Control;

/**
 * Dashboard „Co zaplatit" (Fáze 3) — přehled kandidátů na platbu za zvolené období, bez
 * předgenerování (payment řádek vzniká lazy, až akcí, viz PaymentService).
 */
final class HomePresenter extends SecuredPresenter
{
	/** Zobrazené období — nastaví actionDefault() (běží před signály i renderem, viz Presenter::run()). */
	private int $year;
	private int $month;

	/**
	 * Indexy zdrojů dashboardu (naplní renderDefault) — čte je amount-form factory bez N+1.
	 * Factory se vytváří až při renderu šablony, kdy jsou mapy naplněné; na POST submitu jsou
	 * prázdné (renderDefault ještě neběžel), factory tam spadne na prázdný prefill — odeslaná
	 * data ho stejně přepíšou.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $servicesById = [];

	/** @var array<int, array<string, mixed>> */
	private array $paymentsByServiceId = [];

	public function __construct(
		private readonly ServiceRepository $serviceRepository,
		private readonly PaymentRepository $paymentRepository,
		private readonly PaymentService $paymentService,
		private readonly FormFactory $formFactory,
		private readonly Clock $clock,
	) {
	}

	public function actionDefault(?int $year = null, ?int $month = null): void
	{
		$today = $this->clock->now();
		$resolvedYear = $year ?? (int) $today->format('Y');
		$resolvedMonth = $month ?? (int) $today->format('n');
		$this->assertPeriodValid($resolvedYear, $resolvedMonth);

		$this->year = $resolvedYear;
		$this->month = $resolvedMonth;
	}

	public function renderDefault(): void
	{
		$services = $this->serviceRepository->findAll();
		$payments = $this->paymentRepository->findByPeriod($this->year, $this->month);

		// Indexy pro amount-form factory (žádný N+1) — factory je čte až při renderu šablony.
		$this->servicesById = [];
		foreach ($services as $service) {
			$this->servicesById[(int) $service['id']] = $service;
		}
		$this->paymentsByServiceId = [];
		foreach ($payments as $payment) {
			$this->paymentsByServiceId[(int) $payment['service_id']] = $payment;
		}

		// Sestavení sekcí a součtů je čistá logika mimo presenter — testováno zvlášť
		// (MonthlyOverviewTest), presenter jen předá data a naviguje.
		$overview = MonthlyOverview::build(
			$this->year,
			$this->month,
			$this->clock->now(),
			$services,
			$payments,
		);

		[$prevYear, $prevMonth] = $this->shiftPeriod($this->year, $this->month, -1);
		[$nextYear, $nextMonth] = $this->shiftPeriod($this->year, $this->month, 1);

		$this->template->year = $this->year;
		$this->template->month = $this->month;
		$this->template->monthName = Months::Names[$this->month];
		$this->template->sections = $overview->sections;
		$this->template->remainingTotal = $overview->remainingTotal;
		$this->template->paidTotal = $overview->paidTotal;
		$this->template->prevYear = $prevYear;
		$this->template->prevMonth = $prevMonth;
		$this->template->prevMonthName = Months::Names[$prevMonth];
		$this->template->nextYear = $nextYear;
		$this->template->nextMonth = $nextMonth;
		$this->template->nextMonthName = Months::Names[$nextMonth];
	}

	// Zaplaceno/Přeskočit mění stav → jen POST signály (automatická same-origin ochrana
	// Nette pro handle* metody, stejný vzor jako ServicePresenter::handleArchive apod.).
	// Období nese samotný odkaz (year/month v query), ne server-side stav presenteru.

	#[Requires(methods: 'POST')]
	public function handlePay(int $serviceId, int $year, int $month): void
	{
		$this->assertPeriodValid($year, $month);
		$this->assertActiveService($serviceId);

		$this->paymentService->markPaid($serviceId, $year, $month);
		$this->flashMessage('Platba byla označena jako zaplacená.');
		$this->redirect('Home:default', ['year' => $year, 'month' => $month]);
	}

	#[Requires(methods: 'POST')]
	public function handleUnpay(int $serviceId, int $year, int $month): void
	{
		$this->assertPeriodValid($year, $month);
		$this->assertActiveService($serviceId);

		$this->paymentService->unmarkPaid($serviceId, $year, $month);
		$this->flashMessage('Platba byla vrácena mezi nezaplacené.');
		$this->redirect('Home:default', ['year' => $year, 'month' => $month]);
	}

	#[Requires(methods: 'POST')]
	public function handleSkip(int $serviceId, int $year, int $month): void
	{
		$this->assertPeriodValid($year, $month);
		$this->assertActiveService($serviceId);

		$this->paymentService->skip($serviceId, $year, $month);
		$this->flashMessage('Platba byla přeskočena.');
		$this->redirect('Home:default', ['year' => $year, 'month' => $month]);
	}

	#[Requires(methods: 'POST')]
	public function handleUnskip(int $serviceId, int $year, int $month): void
	{
		$this->assertPeriodValid($year, $month);
		$this->assertActiveService($serviceId);

		$this->paymentService->unskip($serviceId, $year, $month);
		$this->flashMessage('Přeskočení bylo zrušeno.');
		$this->redirect('Home:default', ['year' => $year, 'month' => $month]);
	}

	/**
	 * Jeden formulář na položku dashboardu (Multiplier, klíč = service_id, viz
	 * `{control "amountForm-$item.service.id"}` v Home/default.latte) — dialog „Upravit
	 * částku". Na rozdíl od jednoduchých handle* výše jde o skutečný Nette Form (validace
	 * částky, přerenderování chyb) s addProtection() stejně jako ServiceForm/CategoryForm.
	 *
	 * @return Multiplier<Form>
	 */
	protected function createComponentAmountForm(): Multiplier
	{
		return new Multiplier(function (string $id): Form {
			$serviceId = (int) $id;
			// Prefill z map naplněných v renderDefault (žádný extra dotaz). Na POST submitu
			// jsou mapy prázdné → fallback 0/'' , odeslaná data prefill stejně přepíšou.
			$payment = $this->paymentsByServiceId[$serviceId] ?? null;
			$service = $this->servicesById[$serviceId] ?? null;
			$currentAmount = $payment !== null ? (int) $payment['amount'] : (int) ($service['amount'] ?? 0);
			$currentNote = $payment !== null ? (string) ($payment['note'] ?? '') : '';

			$form = $this->formFactory->create();

			$form->addText('amount', 'Částka (Kč)')
				->setRequired('Zadejte částku.')
				->setDefaultValue(Money::toInputCzk($currentAmount))
				->addRule(
					static fn(Control $control): bool => Money::parseCzk($control->getValue()) !== null,
					'Zadejte platnou částku.',
				);

			$form->addTextArea('note', 'Poznámka')
				->setDefaultValue($currentNote)
				->addRule(Form::MaxLength, 'Poznámka může mít nejvýše %d znaků.', 500);

			$form->addSubmit('send', 'Uložit částku');
			$form->addProtection('Vypršel časový limit, zkuste to prosím znovu.');

			$form->onSuccess[] = function (Form $form, \stdClass $values) use ($serviceId): void {
				$this->assertActiveService($serviceId);

				$amount = Money::parseCzk($values->amount);
				if ($amount === null) {
					// Nemělo by nastat (addRule výše to už zachytí) — obranná pojistka pro PHPStan.
					$form->addError('Neplatná částka.');

					return;
				}

				$note = trim($values->note) === '' ? null : trim($values->note);
				$this->paymentService->setAmount($serviceId, $this->year, $this->month, $amount, $note);
				$this->flashMessage('Částka byla upravena.');
				$this->redirect('Home:default', ['year' => $this->year, 'month' => $this->month]);
			};

			return $form;
		});
	}

	private function assertPeriodValid(int $year, int $month): void
	{
		if ($month < 1 || $month > 12 || !YearRange::isValid($year)) {
			$this->error('Neplatné období.');
		}
	}

	/**
	 * Platební akce se smí týkat jen aktivní (nearchivované) služby — jinak 404. Brání
	 * craftnutému POST signálu nad archivovanou/neexistující službou (tichý insert řádku).
	 */
	private function assertActiveService(int $serviceId): void
	{
		if ($this->serviceRepository->findActive($serviceId) === null) {
			$this->error('Služba nenalezena.');
		}
	}

	/** @return array{0: int, 1: int} */
	private function shiftPeriod(int $year, int $month, int $delta): array
	{
		$total = $year * 12 + ($month - 1) + $delta;

		return [intdiv($total, 12), $total % 12 + 1];
	}
}
