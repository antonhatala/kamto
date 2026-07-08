<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Tovární metoda pro formuláře s přednastaveným českým překladačem hlášek (FormTranslator).
 * Presentery vytvářejí formuláře přes ni, ať vestavěné validační hlášky Nette nechodí anglicky.
 */
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
