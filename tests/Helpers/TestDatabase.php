<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Database\PdoSqliteDb;
use RuntimeException;

/** Sdílený test helper — čerstvá dočasná SQLite DB se schématem z migrations/001_init.sql. */
final class TestDatabase
{
	public static function create(): PdoSqliteDb
	{
		$path = tempnam(sys_get_temp_dir(), 'kamto-test-');
		if ($path === false) {
			throw new RuntimeException('Nelze vytvořit dočasný soubor pro test DB.');
		}

		$db = new PdoSqliteDb($path);
		$db->executeScript((string) file_get_contents(self::migrationsDir() . '/001_init.sql'));

		return $db;
	}

	public static function migrationsDir(): string
	{
		return __DIR__ . '/../../migrations';
	}
}
