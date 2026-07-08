<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\CategoryRepository;
use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Payment\YearHeatmap;
use App\Payment\YearSummary;
use App\Support\Clock;
use App\Support\Months;
use App\Support\YearRange;

/**
 * Roční přehledy (Fáze 4) — souhrn a heatmapa služeb × měsíců za zvolený rok. Čistě read-only
 * (GET, žádné handle* signály) — samotná agregace je v App\Payment\YearSummary a
 * App\Payment\YearHeatmap, presenter jen načte data (vč. archivovaných služeb — historická
 * pravda, viz YearSummary) a naviguje mezi roky.
 */
final class OverviewPresenter extends SecuredPresenter
{
	/** Zobrazený rok — nastaví actionDefault() (běží před renderem, viz Presenter::run()). */
	private int $year;

	public function __construct(
		private readonly ServiceRepository $serviceRepository,
		private readonly PaymentRepository $paymentRepository,
		private readonly CategoryRepository $categoryRepository,
		private readonly Clock $clock,
	) {
	}

	public function actionDefault(?int $year = null): void
	{
		$resolvedYear = $year ?? (int) $this->clock->now()->format('Y');
		if (!YearRange::isValid($resolvedYear)) {
			$this->error('Neplatný rok.');
		}

		$this->year = $resolvedYear;
	}

	public function renderDefault(): void
	{
		// findAll(true) vč. archivovaných — zaplacené platby archivované služby stále patří
		// do "letos zaplaceno" (historická pravda), jen se nepočítají do aktuálních závazků.
		$services = $this->serviceRepository->findAll(true);
		$payments = $this->paymentRepository->findByYear($this->year);
		$categories = $this->categoryRepository->findAll();
		$today = $this->clock->now();

		$this->template->year = $this->year;
		$this->template->prevYear = $this->year - 1;
		$this->template->nextYear = $this->year + 1;
		$this->template->monthNames = Months::Names;
		$this->template->summary = YearSummary::build($this->year, $today, $services, $payments, $categories);
		$this->template->heatmap = YearHeatmap::build($this->year, $today, $services, $payments, $categories);
	}
}
