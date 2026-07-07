<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;

/**
 * Common ancestor for all presenters — resolves templates from app/Templates/
 * instead of Nette's default "templates next to presenter" convention.
 */
abstract class BasePresenter extends Presenter
{
	public function formatTemplateFiles(): array
	{
		return [
			__DIR__ . "/../Templates/{$this->getName()}/{$this->getView()}.latte",
		];
	}

	public function formatLayoutTemplateFiles(): array
	{
		return [
			__DIR__ . '/../Templates/@layout.latte',
		];
	}
}
