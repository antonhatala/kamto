<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Model\CategoryRepository;
use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Payment\CategoryDisplay;
use App\Payment\ServiceHistory;
use App\Support\Clock;
use App\Support\Money;
use App\Support\Months;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Forms\Control;

final class ServicePresenter extends SecuredPresenter
{
	/** @var array<string, mixed>|null */
	private ?array $editedService = null;

	/** @var array<string, mixed> */
	private array $detailedService;

	public function __construct(
		private readonly ServiceRepository $serviceRepository,
		private readonly CategoryRepository $categoryRepository,
		private readonly PaymentRepository $paymentRepository,
		private readonly FormFactory $formFactory,
		private readonly Clock $clock,
	) {
	}

	public function renderDefault(): void
	{
		$services = $this->serviceRepository->findAll();
		$archivedServices = $this->serviceRepository->findArchived();

		$this->template->services = $services;
		$this->template->archivedServices = $archivedServices;
		$this->template->archivedCount = count($archivedServices);
		$this->template->categoriesById = $this->categoryRepository->findAllById();
		$this->template->isEmpty = $services === [] && $archivedServices === [];
	}

	public function actionAdd(): void
	{
		$this->setView('form');
	}

	public function actionEdit(int $id): void
	{
		$service = $this->serviceRepository->find($id);
		if ($service === null) {
			$this->error('Služba nenalezena.');
		}

		$this->editedService = $service;
		$this->setView('form');
	}

	public function renderForm(): void
	{
		$this->template->service = $this->editedService;
	}

	public function actionDetail(int $id): void
	{
		$service = $this->serviceRepository->find($id);
		if ($service === null) {
			$this->error('Služba nenalezena.');
		}

		$this->detailedService = $service;
	}

	public function renderDetail(): void
	{
		$service = $this->detailedService;
		$payments = $this->paymentRepository->findByService((int) $service['id']);
		$category = $service['category_id'] !== null ? $this->categoryRepository->find((int) $service['category_id']) : null;

		$this->template->service = $service;
		$this->template->category = CategoryDisplay::resolve($category);
		$this->template->monthNames = Months::Names;
		$this->template->history = ServiceHistory::build($this->clock->now(), $service, $payments);
	}

	#[Requires(methods: 'POST')]
	public function handleArchive(int $id): void
	{
		if ($this->serviceRepository->find($id) === null) {
			$this->error('Služba nenalezena.');
		}

		$this->serviceRepository->archive($id);
		$this->flashMessage('Služba byla archivována.');
		$this->redirect('Service:default');
	}

	#[Requires(methods: 'POST')]
	public function handleReactivate(int $id): void
	{
		if ($this->serviceRepository->find($id) === null) {
			$this->error('Služba nenalezena.');
		}

		$this->serviceRepository->reactivate($id);
		$this->flashMessage('Služba byla obnovena.');
		$this->redirect('Service:default');
	}

	protected function createComponentServiceForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Název')
			->setRequired('Zadejte název.')
			->addRule(Form::MaxLength, 'Název může mít nejvýše %d znaků.', 80);

		$form->addText('amount', 'Částka (Kč)')
			->setHtmlAttribute('inputmode', 'decimal')
			->setRequired('Zadejte částku.')
			->addRule(
				static fn(Control $control, mixed $arg = null): bool => Money::parseCzk($control->getValue()) !== null,
				'Zadejte platnou částku — kladné číslo, max. 2 desetinná místa, do 100 000 000 Kč.',
			);

		$form->addRadioList('period', 'Perioda', ['monthly' => 'Měsíčně', 'yearly' => 'Ročně'])
			->setRequired('Vyberte periodu.')
			->setDefaultValue('monthly');

		$form->addCheckbox('is_sliding', 'Platím kdykoliv v měsíci');

		$form->addInteger('due_day', 'Den splatnosti')
			->addRule(Form::Range, 'Den splatnosti musí být 1–31.', [1, 31]);

		$form->addSelect('due_month', 'Měsíc splatnosti', Months::Names)
			->setPrompt('Vyberte měsíc');

		$categoryOptions = ['' => 'Bez kategorie'];
		foreach ($this->categoryRepository->findAll() as $category) {
			$categoryOptions[(string) $category['id']] = $category['name'];
		}

		$form->addSelect('category_id', 'Kategorie', $categoryOptions)
			->setDefaultValue('');

		if ($this->editedService !== null) {
			$editedIsSliding = (int) $this->editedService['is_sliding'] === 1;

			$form->setDefaults([
				'name' => $this->editedService['name'],
				'amount' => Money::toInputCzk((int) $this->editedService['amount']),
				'period' => $this->editedService['period'],
				'due_day' => $editedIsSliding ? null : $this->editedService['due_day'],
				'due_month' => $this->editedService['due_month'] !== null ? (int) $this->editedService['due_month'] : null,
				'category_id' => $this->editedService['category_id'] !== null ? (string) $this->editedService['category_id'] : '',
				'is_sliding' => $editedIsSliding,
			]);
		}

		$form->addSubmit('send', 'Uložit');
		$form->addProtection('Vypršel časový limit, zkuste to prosím znovu.');

		$form->onSuccess[] = [$this, 'serviceFormSucceeded'];

		return $form;
	}

	public function serviceFormSucceeded(Form $form, \stdClass $values): void
	{
		$amount = Money::parseCzk($values->amount);
		if ($amount === null) {
			$form->addError('Neplatná částka.');

			return;
		}

		$isYearly = $values->period === 'yearly';
		$isSliding = $values->period === 'monthly' && $values->is_sliding;

		if ($isYearly && $values->due_month === null) {
			$form->addError('Pro roční periodu vyberte měsíc splatnosti.');

			return;
		}

		if (!$isSliding && $values->due_day === null) {
			$form->addError('Zadejte den splatnosti.');

			return;
		}

		$categoryId = $values->category_id === '' || $values->category_id === null
			? null
			: (int) $values->category_id;

		$data = [
			'name' => trim($values->name),
			'amount' => $amount,
			'period' => $values->period,
			'due_day' => $isSliding ? 1 : (int) $values->due_day,
			'due_month' => $isYearly ? (int) $values->due_month : null,
			'category_id' => $categoryId,
			'is_sliding' => $isSliding ? 1 : 0,
		];

		if ($this->editedService !== null) {
			$this->serviceRepository->update((int) $this->editedService['id'], $data);
			$this->flashMessage('Služba byla upravena.');
		} else {
			$this->serviceRepository->insert($data);
			$this->flashMessage('Služba byla přidána.');
		}

		$this->redirect('Service:default');
	}
}
