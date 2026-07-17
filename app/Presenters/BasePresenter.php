<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;

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
