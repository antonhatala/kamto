<?php

declare(strict_types=1);

namespace App\Export;

/**
 * Čistý, doménově nevědomý CSV writer — malé rozhraní (hlavička + řádky řetězců dovnitř,
 * hotový CSV string ven), žádná DB ani znalost plateb/služeb (ta je v App\Payment\PaymentExport).
 * Formát cílí na CZ Excel: středník jako oddělovač, UTF-8 BOM (bez něj Excel hádá kódování
 * a diakritika se rozsype), CRLF konce řádků (RFC 4180).
 *
 * Bezpečnost (CSV/formula injection, viz OWASP): buňka, kterou by Excel/Sheets vyhodnotil
 * jako vzorec (začíná `=`/`+`/`-`/`@`) nebo jako řídicí znak (tab/CR), se neutralizuje
 * prefixem apostrofu — platí pro VŠECHNY buňky vč. hlavičky, protože jména služeb/kategorií
 * i poznámky jsou uživatelský vstup. Escapování aplikuje `escapeCell()` na každou buňku
 * samostatně, řádky samotné nikdy nejsou "surový" string zvenčí.
 */
final class CsvExporter
{
	/**
	 * @param list<string> $header sloupce hlavičky (české popisky)
	 * @param list<list<string>> $rows datové řádky, stejný počet buněk jako $header
	 */
	public static function export(array $header, array $rows): string
	{
		$lines = [self::formatRow($header)];
		foreach ($rows as $row) {
			$lines[] = self::formatRow($row);
		}

		// BOM na začátek (Excel na Windows bez něj otevře UTF-8 jako Windows-1250 -> zpřeházená
		// diakritika) + CRLF za KAŽDÝM řádkem vč. posledního (RFC 4180 doporučuje, Excel to čeká).
		return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
	}

	/** @param list<string> $cells */
	private static function formatRow(array $cells): string
	{
		return implode(';', array_map(self::escapeCell(...), $cells));
	}

	/**
	 * Neutralizace CSV/formula injection (prefix `'`) + RFC 4180 quoting. Pořadí je záměrné:
	 * prefix `'` nikdy sám o sobě nevyvolá potřebu quotování (není to `;`/`"`/newline), takže
	 * quotovací test za ním dá stejný výsledek, jako by běžel na původní hodnotě.
	 */
	private static function escapeCell(string $value): string
	{
		// Excel/Sheets spustí jako vzorec buňku začínající =, +, -, @; tab/CR na začátku je
		// stejná třída útoku (viz OWASP CSV Injection) — sem patří leda user-controlled
		// vstup (jméno služby/kategorie, poznámka), nikdy naše vlastní generovaná pole.
		if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
			$value = "'" . $value;
		}

		// RFC 4180: pole obsahující oddělovač, uvozovky nebo newline se obalí uvozovkami,
		// vnitřní uvozovky se zdvojí.
		if (preg_match('/[;"\r\n]/', $value) === 1) {
			$value = '"' . str_replace('"', '""', $value) . '"';
		}

		return $value;
	}
}
