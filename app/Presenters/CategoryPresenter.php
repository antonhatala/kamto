<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Model\CategoryRepository;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;

final class CategoryPresenter extends SecuredPresenter
{
	private const array Palette = [
		'#c1622e' => 'Terakotová',
		'#2168a3' => 'Modrá',
		'#c9972e' => 'Hořčicová',
		'#8c4f8c' => 'Švestková',
		'#1f8f4f' => 'Zelená',
		'#c15a7a' => 'Růžová',
		'#7a8a2e' => 'Olivová',
		'#188f77' => 'Petrolejová',
	];

	/** @var array<string, mixed>|null */
	private ?array $editedCategory = null;

	public function __construct(
		private readonly CategoryRepository $categoryRepository,
		private readonly FormFactory $formFactory,
	) {
	}

	public function renderDefault(): void
	{
		$categories = $this->categoryRepository->findAll();

		$this->template->categories = array_map(
			fn(array $category): array => $category + [
				'serviceCount' => $this->categoryRepository->countServices((int) $category['id']),
			],
			$categories,
		);
	}

	public function actionAdd(): void
	{
		$this->setView('form');
	}

	public function actionEdit(int $id): void
	{
		$category = $this->categoryRepository->find($id);
		if ($category === null) {
			$this->error('Kategorie nenalezena.');
		}

		$this->editedCategory = $category;
		$this->setView('form');
	}

	public function renderForm(): void
	{
		$this->template->category = $this->editedCategory;
		$this->template->palette = self::Palette;
	}

	public function actionDelete(int $id): void
	{
		$category = $this->categoryRepository->find($id);
		if ($category === null) {
			$this->error('Kategorie nenalezena.');
		}

		$this->template->category = $category;
		$this->template->serviceCount = $this->categoryRepository->countServices($id);
	}

	#[Requires(methods: 'POST')]
	public function handleDelete(int $id): void
	{
		if ($this->categoryRepository->find($id) === null) {
			$this->error('Kategorie nenalezena.');
		}

		$this->categoryRepository->delete($id);
		$this->flashMessage('Kategorie byla smazána.');
		$this->redirect('Category:default');
	}

	protected function createComponentCategoryForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Název')
			->setRequired('Zadejte název.')
			->addRule(Form::MaxLength, 'Název může mít nejvýše %d znaků.', 40);

		$form->addRadioList('color', 'Barva', self::Palette)
			->setRequired('Vyberte barvu.')
			->setDefaultValue(array_key_first(self::Palette));

		if ($this->editedCategory !== null) {
			$form->setDefaults([
				'name' => $this->editedCategory['name'],
				'color' => $this->editedCategory['color'],
			]);
		}

		$form->addSubmit('send', 'Uložit');
		$form->addProtection('Vypršel časový limit, zkuste to prosím znovu.');

		$form->onSuccess[] = [$this, 'categoryFormSucceeded'];

		return $form;
	}

	public function categoryFormSucceeded(Form $form, \stdClass $values): void
	{
		$data = [
			'name' => trim($values->name),
			'color' => $values->color,
		];

		if ($this->editedCategory !== null) {
			$data['sort_order'] = (int) $this->editedCategory['sort_order'];
			$this->categoryRepository->update((int) $this->editedCategory['id'], $data);
			$this->flashMessage('Kategorie byla upravena.');
		} else {
			$data['sort_order'] = $this->categoryRepository->nextSortOrder();
			$this->categoryRepository->insert($data);
			$this->flashMessage('Kategorie byla přidána.');
		}

		$this->redirect('Category:default');
	}
}
