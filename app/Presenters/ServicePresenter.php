<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Model\CategoryRepository;
use App\Model\ServiceRepository;
use App\Support\Money;
use App\Support\Months;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Forms\Control;

/** Seznam/CRUD služeb (šablon plateb) — archivace/reaktivace a ruční řazení, Fáze 2. */
final class ServicePresenter extends SecuredPresenter
{
	/** @var array<string, mixed>|null Editovaná služba (null v `add`), viz actionEdit(). */
	private ?array $editedService = null;

	public function __construct(
		private readonly ServiceRepository $serviceRepository,
		private readonly CategoryRepository $categoryRepository,
		private readonly FormFactory $formFactory,
	) {
	}

	public function renderDefault(): void
	{
		$services = $this->serviceRepository->findAll();
		$archivedServices = $this->serviceRepository->findArchived();

		$this->template->services = $services;
		$this->template->archivedServices = $archivedServices;
		$this->template->archivedCount = count($archivedServices);
		$this->template->categoriesById = $this->indexCategoriesById();
		// Onboarding prázdný stav — žádná služba vůbec (aktivní ani archivovaná).
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

	// Archivace/reaktivace/řazení mění stav → jen POST signály (automatická same-origin
	// ochrana Nette pro `handle*` metody, viz Nette\Application\UI\AccessPolicy).

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

	#[Requires(methods: 'POST')]
	public function handleMoveUp(int $id): void
	{
		$this->swapWithNeighbor($id, -1);
		$this->redirect('Service:default');
	}

	#[Requires(methods: 'POST')]
	public function handleMoveDown(int $id): void
	{
		$this->swapWithNeighbor($id, 1);
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

		$form->addInteger('due_day', 'Den splatnosti')
			->setRequired('Zadejte den splatnosti.')
			->addRule(Form::Range, 'Den splatnosti musí být 1–31.', [1, 31]);

		// due_month je povinný jen pro roční periodu — vynuceno v serviceFormSucceeded(),
		// klient nemusí mít JS, aby pole schoval/zpřístupnil podle zvolené periody.
		$form->addSelect('due_month', 'Měsíc splatnosti', Months::Names)
			->setPrompt('Vyberte měsíc');

		$categoryOptions = ['' => 'Bez kategorie'];
		foreach ($this->categoryRepository->findAll() as $category) {
			$categoryOptions[(string) $category['id']] = $category['name'];
		}

		$form->addSelect('category_id', 'Kategorie', $categoryOptions)
			->setDefaultValue('');

		$form->addText('icon', 'Ikona (emoji)')
			->addRule(
				static function (Control $control, mixed $arg = null): bool {
					$value = trim($control->getValue());

					return $value === '' || preg_match('/^\X$/u', $value) === 1;
				},
				'Ikona může být jen jeden znak/emoji.',
			);

		$form->addTextArea('note', 'Poznámka')
			->addRule(Form::MaxLength, 'Poznámka může mít nejvýše %d znaků.', 500);

		if ($this->editedService !== null) {
			$form->setDefaults([
				'name' => $this->editedService['name'],
				'amount' => Money::toInputCzk((int) $this->editedService['amount']),
				'period' => $this->editedService['period'],
				'due_day' => $this->editedService['due_day'],
				// NULL (měsíční perioda) musí zůstat null — select se setPrompt() prázdný string nepřijme
				'due_month' => $this->editedService['due_month'] !== null ? (int) $this->editedService['due_month'] : null,
				'category_id' => $this->editedService['category_id'] !== null ? (string) $this->editedService['category_id'] : '',
				'icon' => $this->editedService['icon'] ?? '',
				'note' => $this->editedService['note'] ?? '',
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
			// Nemělo by nastat (addRule výše to už zachytí) — obranná pojistka pro PHPStan.
			$form->addError('Neplatná částka.');

			return;
		}

		$isYearly = $values->period === 'yearly';
		if ($isYearly && $values->due_month === null) {
			$form->addError('Pro roční periodu vyberte měsíc splatnosti.');

			return;
		}

		$categoryId = $values->category_id === '' || $values->category_id === null
			? null
			: (int) $values->category_id;

		$data = [
			'name' => trim($values->name),
			'amount' => $amount,
			'period' => $values->period,
			'due_day' => $values->due_day,
			// Měsíční perioda due_month nikdy neukládá, i kdyby v requestu přišel (vynuceno na serveru).
			'due_month' => $isYearly ? (int) $values->due_month : null,
			'category_id' => $categoryId,
			'icon' => ($icon = trim($values->icon)) === '' ? null : $icon,
			'note' => ($note = trim($values->note)) === '' ? null : $note,
		];

		if ($this->editedService !== null) {
			// Editace neměří created_at/is_archived/archived_at/sort_order.
			$data['sort_order'] = (int) $this->editedService['sort_order'];
			$this->serviceRepository->update((int) $this->editedService['id'], $data);
			$this->flashMessage('Služba byla upravena.');
		} else {
			$data['sort_order'] = $this->serviceRepository->nextSortOrder();
			$this->serviceRepository->insert($data);
			$this->flashMessage('Služba byla přidána.');
		}

		$this->redirect('Service:default');
	}

	/** Prohodí sort_order se sousedem v aktivním (nearchivovaném) seznamu. */
	private function swapWithNeighbor(int $id, int $offset): void
	{
		$services = $this->serviceRepository->findAll();
		$position = array_search($id, array_column($services, 'id'), true);
		if ($position === false) {
			return; // Mezitím smazáno/archivováno — tiché no-op.
		}

		$targetPosition = $position + $offset;
		if ($targetPosition < 0 || $targetPosition >= count($services)) {
			return; // Krajní pozice — no-op (tlačítko má být v UI skryté).
		}

		$this->serviceRepository->swapSortOrder(
			(int) $services[$position]['id'],
			(int) $services[$targetPosition]['id'],
		);
	}

	/** @return array<int, array<string, mixed>> Kategorie indexované podle id — pro zobrazení u služby bez N+1. */
	private function indexCategoriesById(): array
	{
		$categoriesById = [];
		foreach ($this->categoryRepository->findAll() as $category) {
			$categoriesById[(int) $category['id']] = $category;
		}

		return $categoriesById;
	}
}
