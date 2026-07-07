<?php

declare(strict_types=1);

namespace App\Database;

use InvalidArgumentException;

/**
 * Vybírá implementaci Db podle configu (config/config.neon parametr `database`). Registrovaná
 * jako DI service factory — viz config.neon `services: database: App\Database\DbFactory::create(%database%)`.
 */
final class DbFactory
{
	/** @param array{driver: string, path: string, url: string, token: string} $database */
	public static function create(array $database): Db
	{
		return match ($database['driver']) {
			'pdo-sqlite' => new PdoSqliteDb($database['path']),
			'libsql' => new LibsqlDb($database['url'], $database['token']),
			default => throw new InvalidArgumentException(
				sprintf("Neznámý database.driver '%s' (očekávám 'pdo-sqlite' nebo 'libsql').", $database['driver']),
			),
		};
	}
}
