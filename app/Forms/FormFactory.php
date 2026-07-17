<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

final class FormFactory
{
	public function __construct(
		private readonly FormTranslator $translator,
	) {
	}

	public function create(): Form
	{
		$form = new Form;
		$form->setTranslator($this->translator);

		return $form;
	}
}
