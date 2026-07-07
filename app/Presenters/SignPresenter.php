<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;

final class SignPresenter extends BasePresenter
{
	public function actionIn(): void
	{
		// Already signed in? The login page makes no sense — go home.
		if ($this->getUser()->isLoggedIn()) {
			$this->redirect('Home:default');
		}
	}

	public function actionOut(): void
	{
		$this->getUser()->logout(true);
		$this->flashMessage('Byli jste odhlášeni.');
		$this->redirect('Sign:in');
	}

	protected function createComponentSignInForm(): Form
	{
		$form = new Form;
		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadejte heslo.');

		$form->addSubmit('send', 'Přihlásit se');
		$form->addProtection('Vypršel časový limit, zkuste to prosím znovu.');

		$form->onSuccess[] = [$this, 'signInFormSucceeded'];

		return $form;
	}

	public function signInFormSucceeded(Form $form, \stdClass $values): void
	{
		try {
			// Single-user app — no username field, the authenticator only checks the password.
			$this->getUser()->login('', $values->password);
			$this->redirect('Home:default');
		} catch (AuthenticationException) {
			$form->addError('Nesprávné heslo.');
		}
	}
}
