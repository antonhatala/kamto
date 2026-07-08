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

/**
 * Roční přehledy (Fáze 4) — souhrn a heatmapa služeb × měsíců za zvolený rok. Čistě read-only
 * (GET, žádné handle* signály) — samotná agregace je v App\Payment\YearSummary a
 * App\Payment\YearHeatmap, presenter jen načte data (vč. archivovaných služeb — historická
 * pravda, viz YearSummary) a naviguje mezi roky.
 *
 * actionExport() (Fáze 5) — CSV export historie plateb za rok. Business logika (řádky,
 * escapování) je mimo presenter: App\Payment\PaymentExport sestaví řádky, App\Export\CsvExporter
 * je serializuje na CSV string; presenter jen načte data a pošle hotový string jako soubor.
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
		$this->year = $this->resolveYear($year);
	}

	/** Rok z parametru (default = aktuální dle Clock), validovaný proti YearRange (jinak 404). */
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

	/**
	 * CSV export historie plateb za rok (Fáze 5) — stejná validace roku jako actionDefault().
	 * Read-only GET, žádný render (přímo posílá soubor a terminuje) — proto vlastní validace
	 * místo sdílení s actionDefault() přes společné $this->year (export nemusí navigovat
	 * mezi roky ani nic renderovat).
	 */
	public function actionExport(?int $year = null): void
	{
		$resolvedYear = $this->resolveYear($year);

		// Stejná data jako actionDefault(): findAll(true) vč. archivu — zaplacená platba
		// archivované služby patří do historie roku stejně jako do "letos zaplaceno" v přehledu.
		$services = $this->serviceRepository->findAll(true);
		$payments = $this->paymentRepository->findByYear($resolvedYear);
		$categories = $this->categoryRepository->findAll();

		$rows = PaymentExport::buildRows($this->clock->now(), $services, $payments, $categories);
		$csv = CsvExporter::export(PaymentExport::Header, $rows);

		// getHttpResponse() vrací rozhraní Http\IResponse — sendAsFile() je jen na konkrétní
		// třídě Response, proto Content-Disposition přímo přes setHeader() (i to je v IResponse).
		$httpResponse = $this->getHttpResponse();
		$httpResponse->setContentType('text/csv', 'utf-8');
		$httpResponse->setHeader('Content-Disposition', sprintf('attachment; filename="kamto-platby-%d.csv"', $resolvedYear));

		$this->sendResponse(new TextResponse($csv));
	}
}
