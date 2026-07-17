<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Model\CategoryRepository;
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

final class HomePresenter extends SecuredPresenter
{
	private int $year;
	private int $month;

	/** @var array<int, array<string, mixed>> */
	private array $servicesById = [];

	/** @var array<int, array<string, mixed>> */
	private array $paymentsByServiceId = [];

	public function __construct(
		private readonly ServiceRepository $serviceRepository,
		private readonly CategoryRepository $categoryRepository,
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

		$this->servicesById = [];
		foreach ($services as $service) {
			$this->servicesById[(int) $service['id']] = $service;
		}
		$this->paymentsByServiceId = [];
		foreach ($payments as $payment) {
			$this->paymentsByServiceId[(int) $payment['service_id']] = $payment;
		}

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
		$this->template->categoriesById = $this->categoryRepository->findAllById();
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

	/** @return Multiplier<Form> */
	protected function createComponentAmountForm(): Multiplier
	{
		return new Multiplier(function (string $id): Form {
			$serviceId = (int) $id;
			$payment = $this->paymentsByServiceId[$serviceId] ?? null;
			$service = $this->servicesById[$serviceId] ?? null;
			$currentAmount = $payment !== null ? (int) $payment['amount'] : (int) ($service['amount'] ?? 0);

			$form = $this->formFactory->create();

			$form->addText('amount', 'Částka (Kč)')
				->setRequired('Zadejte částku.')
				->setDefaultValue(Money::toInputCzk($currentAmount))
				->addRule(
					static fn(Control $control): bool => Money::parseCzk($control->getValue()) !== null,
					'Zadejte platnou částku.',
				);

			$form->addSubmit('send', 'Zaplatit');
			$form->addProtection('Vypršel časový limit, zkuste to prosím znovu.');

			$form->onSuccess[] = function (Form $form, \stdClass $values) use ($serviceId): void {
				$this->assertActiveService($serviceId);

				$amount = Money::parseCzk($values->amount);
				if ($amount === null) {
					$form->addError('Neplatná částka.');

					return;
				}

				$this->paymentService->setAmount($serviceId, $this->year, $this->month, $amount);
				$this->paymentService->markPaid($serviceId, $this->year, $this->month);
				$this->flashMessage('Platba byla zaplacena s upravenou částkou.');
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
