<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\FormFactory;
use App\Security\LoginThrottle;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;

final class SignPresenter extends BasePresenter
{
	public function __construct(
		private readonly LoginThrottle $loginThrottle,
		private readonly FormFactory $formFactory,
	) {
	}

	public function actionIn(): void
	{
		// Already signed in? The login page makes no sense — go home.
		if ($this->getUser()->isLoggedIn()) {
			$this->redirect('Home:default');
		}
	}

	// Odhlášení mění stav (invaliduje session) → jen POST + same-origin (CSRF), viz šablona
	// (formulář/tlačítko v hlavičce), ne obyčejný <a href> GET odkaz.
	#[Requires(methods: 'POST', sameOrigin: true)]
	public function actionOut(): void
	{
		$this->getUser()->logout(true);
		$this->flashMessage('Byli jste odhlášeni.');
		$this->redirect('Sign:in');
	}

	protected function createComponentSignInForm(): Form
	{
		$form = $this->formFactory->create();
		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadejte heslo.');

		$form->addSubmit('send', 'Přihlásit se');
		$form->addProtection('Vypršel časový limit, zkuste to prosím znovu.');

		$form->onSuccess[] = [$this, 'signInFormSucceeded'];

		return $form;
	}

	public function signInFormSucceeded(Form $form, \stdClass $values): void
	{
		$wait = $this->loginThrottle->secondsUntilRetry();
		if ($wait > 0) {
			$form->addError(sprintf('Příliš mnoho neúspěšných pokusů. Zkuste to znovu za %d s.', $wait));

			return;
		}

		try {
			// Single-user app — no username field, the authenticator only checks the password.
			$this->getUser()->login('', $values->password);
			$this->loginThrottle->registerSuccess();
			$this->redirect('Home:default');
		} catch (AuthenticationException) {
			$this->loginThrottle->registerFailure();
			$form->addError('Nesprávné heslo.');
		}
	}
}
