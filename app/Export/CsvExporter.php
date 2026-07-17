<?php

declare(strict_types=1);

namespace App\Export;

final class CsvExporter
{
	/**
	 * @param list<string> $header
	 * @param list<list<string>> $rows
	 */
	public static function export(array $header, array $rows): string
	{
		$lines = [self::formatRow($header)];
		foreach ($rows as $row) {
			$lines[] = self::formatRow($row);
		}

		return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
	}

	/** @param list<string> $cells */
	private static function formatRow(array $cells): string
	{
		return implode(';', array_map(self::escapeCell(...), $cells));
	}

	private static function escapeCell(string $value): string
	{
		if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
			$value = "'" . $value;
		}

		if (preg_match('/[;"\r\n]/', $value) === 1) {
			$value = '"' . str_replace('"', '""', $value) . '"';
		}

		return $value;
	}
}
