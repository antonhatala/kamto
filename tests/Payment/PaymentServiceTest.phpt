<?php

declare(strict_types=1);

use App\Model\PaymentRepository;
use App\Model\ServiceRepository;
use App\Payment\PaymentService;
use Tester\Assert;
use Tests\Helpers\FakeClock;
use Tests\Helpers\TestDatabase;

require __DIR__ . '/../bootstrap.php';

$db = TestDatabase::create();
$services = new ServiceRepository($db);
$payments = new PaymentRepository($db);
$clock = new FakeClock(new DateTimeImmutable('2026-07-08'));
$paymentService = new PaymentService($payments, $services, $clock);

$monthlyId = $services->insert([
	'name' => 'Internet',
	'amount' => 60000,
	'period' => 'monthly',
	'due_day' => 12,
]);

// Lazy vznik — markPaid na období bez existujícího řádku ho založí (snapshot service.amount,
// due_date dopočtené DueDateCalculator) a rovnou označí jako zaplacené.
$paymentService->markPaid($monthlyId, 2026, 7);
$row = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::notSame(null, $row);
Assert::same('2026-07-12', $row['due_date']);
Assert::same(60000, $row['amount']);
Assert::same('2026-07-08', $row['paid_date']);
Assert::null($row['skipped_at']);

// E13 — opakované volání nesmí porušit UNIQUE(service_id, period_year, period_month): pořád
// jde o ten samý řádek (stejné id), ne duplikát/chyba.
$paymentService->markPaid($monthlyId, 2026, 7);
$sameRow = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::same($row['id'], $sameRow['id']);
Assert::count(1, $payments->findByService($monthlyId));

// unmarkPaid -> paid_date zpět na NULL, řádek (historie) zůstává.
$paymentService->unmarkPaid($monthlyId, 2026, 7);
Assert::null($payments->findByServiceAndPeriod($monthlyId, 2026, 7)['paid_date']);

// skip / unskip.
$paymentService->skip($monthlyId, 2026, 7);
$skipped = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::same('2026-07-08', $skipped['skipped_at']);
Assert::null($skipped['paid_date']);

$paymentService->unskip($monthlyId, 2026, 7);
Assert::null($payments->findByServiceAndPeriod($monthlyId, 2026, 7)['skipped_at']);

// setAmount — upraví jen tenhle jeden řádek; pozdější (dřívější) změna service.amount se do
// existující platby nepropíše (snapshot).
$paymentService->setAmount($monthlyId, 2026, 7, 65000, 'Zdražili o 50 Kč');
$adjusted = $payments->findByServiceAndPeriod($monthlyId, 2026, 7);
Assert::same(65000, $adjusted['amount']);
Assert::same('Zdražili o 50 Kč', $adjusted['note']);
Assert::same(60000, $services->find($monthlyId)['amount']);

// Roční služba: platba se eviduje na její due_month, ne na libovolný předaný měsíc.
$yearlyId = $services->insert([
	'name' => 'Doména',
	'amount' => 30000,
	'period' => 'yearly',
	'due_day' => 15,
	'due_month' => 3,
]);

$paymentService->markPaid($yearlyId, 2026, 3);
$yearlyRow = $payments->findByServiceAndPeriod($yearlyId, 2026, 3);
Assert::notSame(null, $yearlyRow);
Assert::same('2026-03-15', $yearlyRow['due_date']);

// I kdyby volající předal jiný měsíc než due_month, upsert period_month přepíše na due_month
// sám — nevznikne nekonzistentní/duplicitní řádek (obranná invariance, ne jen spoléhání na
// to, že dashboard vždy pošle správný měsíc).
$otherYearlyId = $services->insert([
	'name' => 'Hosting',
	'amount' => 120000,
	'period' => 'yearly',
	'due_day' => 1,
	'due_month' => 9,
]);
$paymentService->markPaid($otherYearlyId, 2027, 5); // "špatný" měsíc
Assert::notSame(null, $payments->findByServiceAndPeriod($otherYearlyId, 2027, 9));
Assert::null($payments->findByServiceAndPeriod($otherYearlyId, 2027, 5));

// Neexistující služba -> výjimka (fail fast), ne tichý no-op.
Assert::exception(
	static fn() => $paymentService->markPaid(99999, 2026, 7),
	InvalidArgumentException::class,
);

// QA#1 — archivovaná služba nesmí přijmout platební signál (jinak tichý insert řádku).
// Přechod skončí výjimkou a nevznikne žádný payment řádek.
$archivedId = $services->insert([
	'name' => 'Zrušené předplatné',
	'amount' => 19900,
	'period' => 'monthly',
	'due_day' => 3,
]);
$services->archive($archivedId);
Assert::exception(
	static fn() => $paymentService->markPaid($archivedId, 2026, 7),
	InvalidArgumentException::class,
);
Assert::exception(
	static fn() => $paymentService->skip($archivedId, 2026, 7),
	InvalidArgumentException::class,
);
Assert::exception(
	static fn() => $paymentService->setAmount($archivedId, 2026, 7, 100, null),
	InvalidArgumentException::class,
);
Assert::count(0, $payments->findByService($archivedId));

// QA#2 — zaplaceno a přeskočeno jsou vzájemně výlučné: pozitivní přechod vynuluje opačný příznak.
$exclId = $services->insert([
	'name' => 'Výlučnost',
	'amount' => 50000,
	'period' => 'monthly',
	'due_day' => 15,
]);

// skip -> markPaid: skipped_at se vynuluje, řádek je zaplacený (ne oba naráz).
$paymentService->skip($exclId, 2026, 8);
$paymentService->markPaid($exclId, 2026, 8);
$afterPay = $payments->findByServiceAndPeriod($exclId, 2026, 8);
Assert::same('2026-07-08', $afterPay['paid_date']);
Assert::null($afterPay['skipped_at']);

// markPaid -> skip: paid_date se vynuluje, řádek je přeskočený.
$paymentService->skip($exclId, 2026, 8);
$afterSkip = $payments->findByServiceAndPeriod($exclId, 2026, 8);
Assert::same('2026-07-08', $afterSkip['skipped_at']);
Assert::null($afterSkip['paid_date']);

// Kříženec skip -> pay -> unpay nesmí spadnout do Přeskočeno: protože pay vynuloval skip,
// následný unpay skončí v čistém stavu (ani zaplaceno, ani přeskočeno) — položka se vrátí
// mezi nezaplacené, ne do sekce Přeskočeno.
$paymentService->skip($exclId, 2026, 9);
$paymentService->markPaid($exclId, 2026, 9);
$paymentService->unmarkPaid($exclId, 2026, 9);
$afterUnpay = $payments->findByServiceAndPeriod($exclId, 2026, 9);
Assert::null($afterUnpay['paid_date']);
Assert::null($afterUnpay['skipped_at']);
