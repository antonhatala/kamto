<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Database\PdoSqliteDb;
use RuntimeException;

final class TestDatabase
{
	public static function create(): PdoSqliteDb
	{
		$path = tempnam(sys_get_temp_dir(), 'kamto-test-');
		if ($path === false) {
			throw new RuntimeException('Nelze vytvořit dočasný soubor pro test DB.');
		}

		$db = new PdoSqliteDb($path);

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
