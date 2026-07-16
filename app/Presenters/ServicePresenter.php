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

/** Seznam/CRUD služeb (šablon plateb) — archivace/reaktivace, Fáze 2; detail s historií
 * plateb, Fáze 4. Řazení je automatické podle dne splatnosti, viz ServiceRepository::findAll(). */
final class ServicePresenter extends SecuredPresenter
{
	/** @var array<string, mixed>|null Editovaná služba (null v `add`), viz actionEdit(). */
	private ?array $editedService = null;

	/**
	 * Zobrazená služba (detail) — nastaví actionDetail() (běží před renderem, viz
	 * Presenter::run()); nenulovatelné jako HomePresenter::$year/$month, ne tristate jako
	 * $editedService výše (ten null legitimně znamená "režim Add", tohle ne).
	 * @var array<string, mixed>
	 */
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

	/**
	 * Detail služby s historií plateb (Fáze 4) — find(), NE findActive(): detail musí fungovat
	 * i pro archivovanou službu (historie zůstává čitelná, viz CONTEXT.md "Archivace").
	 */
	public function actionDetail(int $id): void
	{
		$service = $this->serviceRepository->find($id);
		if ($service === null) {
			$this->error('Služba nenalezena.');
		}

		$this->detailedService = $service; // $this->error() výše vždy ukončí request (throws) — sem se dostane jen non-null.
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

	// Archivace/reaktivace mění stav → jen POST signály (automatická same-origin ochrana Nette
	// pro `handle*` metody, viz Nette\Application\UI\AccessPolicy).

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

		// Klouzavá služba (jen měsíční perioda, viz CONTEXT.md) nemá pevný den splatnosti —
		// checkbox je ortogonální k periodě, roční klouzavá neexistuje (vynuceno v
		// serviceFormSucceeded(), na formuláři se u roční jen ignoruje).
		$form->addCheckbox('is_sliding', 'Platím kdykoliv v měsíci');

		// Povinnost due_day je podmíněná (yearly vždy, monthly jen bez klouzavosti) — stejný
		// vzor jako due_month níže: NENÍ setRequired() na formuláři, vynucuje se až v
		// serviceFormSucceeded(). Range rule se u nevyplněného nepovinného pole nekontroluje
		// (Nette\Forms\Rules::validate() přeskočí non-Filled pravidla, když control není
		// required a je prázdný) — klouzavá služba tak projde bez due_day.
		$form->addInteger('due_day', 'Den splatnosti')
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
			$editedIsSliding = (int) $this->editedService['is_sliding'] === 1;

			$form->setDefaults([
				'name' => $this->editedService['name'],
				'amount' => Money::toInputCzk((int) $this->editedService['amount']),
				'period' => $this->editedService['period'],
				// Klouzavá služba má v DB jen placeholder due_day=1 (nikdy se nepoužije) —
				// uživateli ho v editaci neukazujeme, pole zůstane prázdné.
				'due_day' => $editedIsSliding ? null : $this->editedService['due_day'],
				// NULL (měsíční perioda) musí zůstat null — select se setPrompt() prázdný string nepřijme
				'due_month' => $this->editedService['due_month'] !== null ? (int) $this->editedService['due_month'] : null,
				'category_id' => $this->editedService['category_id'] !== null ? (string) $this->editedService['category_id'] : '',
				'icon' => $this->editedService['icon'] ?? '',
				'note' => $this->editedService['note'] ?? '',
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
			// Nemělo by nastat (addRule výše to už zachytí) — obranná pojistka pro PHPStan.
			$form->addError('Neplatná částka.');

			return;
		}

		$isYearly = $values->period === 'yearly';
		// Klouzavost dává smysl jen pro měsíční periodu — u roční se checkbox ignoruje (roční
		// klouzavá neexistuje, viz CONTEXT.md).
		$isSliding = $values->period === 'monthly' && $values->is_sliding;

		if ($isYearly && $values->due_month === null) {
			$form->addError('Pro roční periodu vyberte měsíc splatnosti.');

			return;
		}

		// Den splatnosti je povinný vždy KROMĚ klouzavé měsíční služby (ta ho vůbec nezadává).
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
			// Klouzavá služba nemá pevný den — ukládá se placeholder 1 (due_day je v DB
			// NOT NULL, ale hodnota se u klouzavé nikdy nepoužije, viz
			// DueDateCalculator::LastDayOfMonth). Zdroj pravdy je is_sliding.
			'due_day' => $isSliding ? 1 : (int) $values->due_day,
			// Měsíční perioda due_month nikdy neukládá, i kdyby v requestu přišel (vynuceno na serveru).
			'due_month' => $isYearly ? (int) $values->due_month : null,
			'category_id' => $categoryId,
			'icon' => ($icon = trim($values->icon)) === '' ? null : $icon,
			'note' => ($note = trim($values->note)) === '' ? null : $note,
			'is_sliding' => $isSliding ? 1 : 0,
		];

		if ($this->editedService !== null) {
			// Editace neměří created_at/is_archived/archived_at.
			$this->serviceRepository->update((int) $this->editedService['id'], $data);
			$this->flashMessage('Služba byla upravena.');
		} else {
			$this->serviceRepository->insert($data);
			$this->flashMessage('Služba byla přidána.');
		}

		$this->redirect('Service:default');
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
