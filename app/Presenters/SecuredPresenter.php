<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Ancestor for presenters that require a logged-in user. Extend this instead
 * of BasePresenter for every future protected section of the app.
 */
abstract class SecuredPresenter extends BasePresenter
{
	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect('Sign:in');
		}
	}
}
