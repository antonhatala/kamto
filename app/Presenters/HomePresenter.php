<?php

declare(strict_types=1);

namespace App\Presenters;

final class HomePresenter extends SecuredPresenter
{
	// Dashboard přibude ve Fázi 3 (přehled plateb) — do té doby jen vstupní bod na seznam služeb.
	public function actionDefault(): void
	{
		$this->redirect('Service:default');
	}
}
