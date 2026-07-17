<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Export\CsvExporter;
use App\Model\CategoryRepository;
use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Payment\PaymentExport;
use App\Payment\YearHeatmap;
use App\Payment\YearSummary;
use App\Support\Clock;
use App\Support\Months;
use App\Support\YearRange;
use Nette\Application\Responses\TextResponse;

final class OverviewPresenter extends SecuredPresenter
{
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
		$this->year = $this->resolveYear($year);
	}

	private function resolveYear(?int $year): int
	{
		$resolvedYear = $year ?? (int) $this->clock->now()->format('Y');
		if (!YearRange::isValid($resolvedYear)) {
			$this->error('Neplatný rok.');
		}

		return $resolvedYear;
	}

	public function renderDefault(): void
	{
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

	public function actionExport(?int $year = null): void
	{
		$resolvedYear = $this->resolveYear($year);

		$services = $this->serviceRepository->findAll(true);
		$payments = $this->paymentRepository->findByYear($resolvedYear);
		$categories = $this->categoryRepository->findAll();

		$rows = PaymentExport::buildRows($this->clock->now(), $services, $payments, $categories);
		$csv = CsvExporter::export(PaymentExport::Header, $rows);

		$httpResponse = $this->getHttpResponse();
		$httpResponse->setContentType('text/csv', 'utf-8');
		$httpResponse->setHeader('Content-Disposition', sprintf('attachment; filename="kamto-platby-%d.csv"', $resolvedYear));

		$this->sendResponse(new TextResponse($csv));
	}
}
