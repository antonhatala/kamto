<?php

declare(strict_types=1);

use App\Export\CsvExporter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$bom = "\xEF\xBB\xBF";

function firstDataLine(string $csv, string $bom): string
{
	$withoutBom = substr($csv, strlen($bom));
	$lines = explode("\r\n", $withoutBom);

	return $lines[1];
}

$empty = CsvExporter::export(['A', 'B'], []);
Assert::same($bom . "A;B\r\n", $empty);

$basic = CsvExporter::export(['Služba', 'Částka (Kč)'], [['Netflix', '199,50']]);
Assert::same($bom . "Služba;Částka (Kč)\r\n" . "Netflix;199,50\r\n", $basic);

Assert::true(str_starts_with($basic, $bom));

Assert::true(str_ends_with($basic, "\r\n"));
Assert::same(2, substr_count($basic, "\r\n"));
Assert::false(str_contains(str_replace("\r\n", '', $basic), "\n"));

Assert::same("'=SUM(A1:A2)", firstDataLine(CsvExporter::export([], [['=SUM(A1:A2)']]), $bom));
Assert::same("'+1+1", firstDataLine(CsvExporter::export([], [['+1+1']]), $bom));
Assert::same("'-1+1", firstDataLine(CsvExporter::export([], [['-1+1']]), $bom));
Assert::same("'@SUM(A1:A2)", firstDataLine(CsvExporter::export([], [['@SUM(A1:A2)']]), $bom));
Assert::same("'\tzáludné", firstDataLine(CsvExporter::export([], [["\tzáludné"]]), $bom));
Assert::same("\"'\rzáludné\"", firstDataLine(CsvExporter::export([], [["\rzáludné"]]), $bom));

Assert::same('Cena -50 %', firstDataLine(CsvExporter::export([], [['Cena -50 %']]), $bom));
Assert::same('', firstDataLine(CsvExporter::export([], [['']]), $bom));

Assert::same('"Nájem; byt"', firstDataLine(CsvExporter::export([], [['Nájem; byt']]), $bom));
Assert::same('"Řekl ""ahoj"""', firstDataLine(CsvExporter::export([], [['Řekl "ahoj"']]), $bom));
Assert::true(str_contains(CsvExporter::export([], [["Řádek1\nŘádek2"]]), "\"Řádek1\nŘádek2\""));

Assert::same("\"'=1;2\"", firstDataLine(CsvExporter::export([], [['=1;2']]), $bom));

$multi = CsvExporter::export(
	['Služba', 'Kategorie', 'Částka (Kč)'],
	[
		['Netflix', 'Zábava', '199,50'],
		['Nájem', 'Bydlení', '12000'],
	],
);
Assert::same(
	$bom . "Služba;Kategorie;Částka (Kč)\r\nNetflix;Zábava;199,50\r\nNájem;Bydlení;12000\r\n",
	$multi,
);
