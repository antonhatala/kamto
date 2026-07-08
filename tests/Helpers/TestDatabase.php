<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Database\PdoSqliteDb;
use RuntimeException;

/** Sdílený test helper — čerstvá dočasná SQLite DB se schématem ze všech migrations/*.sql. */
final class TestDatabase
{
	public static function create(): PdoSqliteDb
	{
		$path = tempnam(sys_get_temp_dir(), 'kamto-test-');
		if ($path === false) {
			throw new RuntimeException('Nelze vytvořit dočasný soubor pro test DB.');
		}

		$db = new PdoSqliteDb($path);

		// Stejný seznam migrací a stejné pořadí jako MigrationRunner — testy tak vždy běží
		// nad aktuálním schématem, ne jen nad 001_init.sql (viz 002_payment_skipped.sql).
		$files = glob(self::migrationsDir() . '/*.sql') ?: [];
		sort($files, SORT_STRING);
		foreach ($files as $file) {
			$db->executeScript((string) file_get_contents($file));
		}

		return $db;
	}

	public static function migrationsDir(): string
	{
		return __DIR__ . '/../../migrations';
	}
}
