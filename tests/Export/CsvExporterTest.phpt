<?php

declare(strict_types=1);

use App\Export\CsvExporter;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$bom = "\xEF\xBB\xBF";

/**
 * Vrátí jediný datový řádek (za hlavičkou) z výstupu export() jako string — pro čitelnost
 * testů níže, kde vstup má vždy přesně jednu buňku/jeden řádek dat.
 */
function firstDataLine(string $csv, string $bom): string
{
	$withoutBom = substr($csv, strlen($bom));
	$lines = explode("\r\n", $withoutBom);

	return $lines[1]; // $lines[0] = hlavička.
}

// --- E1 Prázdná data — jen hlavička, žádný pád, BOM + CRLF i bez řádků. ---
$empty = CsvExporter::export(['A', 'B'], []);
Assert::same($bom . "A;B\r\n", $empty);

// --- Základní řádek — bez speciálních znaků, středník jako oddělovač. ---
$basic = CsvExporter::export(['Služba', 'Částka (Kč)'], [['Netflix', '199,50']]);
Assert::same($bom . "Služba;Částka (Kč)\r\n" . "Netflix;199,50\r\n", $basic);

// --- BOM na začátku výstupu (ne jen v první buňce). ---
Assert::true(str_starts_with($basic, $bom));

// --- CRLF jako konec řádku, vč. posledního řádku, žádné osamocené LF. ---
Assert::true(str_ends_with($basic, "\r\n"));
Assert::same(2, substr_count($basic, "\r\n")); // hlavička + 1 datový řádek = 2x CRLF.
Assert::false(str_contains(str_replace("\r\n", '', $basic), "\n"));

// --- CSV INJECTION — buňka začínající =, +, -, @, tab nebo CR se neutralizuje prefixem apostrofu. ---
Assert::same("'=SUM(A1:A2)", firstDataLine(CsvExporter::export([], [['=SUM(A1:A2)']]), $bom));
Assert::same("'+1+1", firstDataLine(CsvExporter::export([], [['+1+1']]), $bom));
Assert::same("'-1+1", firstDataLine(CsvExporter::export([], [['-1+1']]), $bom));
Assert::same("'@SUM(A1:A2)", firstDataLine(CsvExporter::export([], [['@SUM(A1:A2)']]), $bom));
Assert::same("'\tzáludné", firstDataLine(CsvExporter::export([], [["\tzáludné"]]), $bom));
// \r spouští zároveň injection prefix (řídicí znak na začátku) i RFC 4180 quoting (newline) -> obojí najednou.
Assert::same("\"'\rzáludné\"", firstDataLine(CsvExporter::export([], [["\rzáludné"]]), $bom));

// --- Neškodná buňka se znaménkem uvnitř (ne na začátku) se NEMĚNÍ. ---
Assert::same('Cena -50 %', firstDataLine(CsvExporter::export([], [['Cena -50 %']]), $bom));
// --- Prázdná buňka projde beze změny (žádné falešné "začíná speciálním znakem"). ---
Assert::same('', firstDataLine(CsvExporter::export([], [['']]), $bom));

// --- RFC 4180 quoting — pole se středníkem/uvozovkou/newline se obalí uvozovkami, "" zdvojení. ---
Assert::same('"Nájem; byt"', firstDataLine(CsvExporter::export([], [['Nájem; byt']]), $bom));
Assert::same('"Řekl ""ahoj"""', firstDataLine(CsvExporter::export([], [['Řekl "ahoj"']]), $bom));
Assert::true(str_contains(CsvExporter::export([], [["Řádek1\nŘádek2"]]), "\"Řádek1\nŘádek2\""));

// --- Injection prefix + quoting zároveň — jméno "=1;2" se prefixne i obalí uvozovkami. ---
Assert::same("\"'=1;2\"", firstDataLine(CsvExporter::export([], [['=1;2']]), $bom));

// --- Víc sloupců i řádků — pořadí a počet buněk zachovány, žádné promíchání. ---
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
