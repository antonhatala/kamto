<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;
use Throwable;

final class LibsqlDb implements Db
{
	private readonly \LibSQL $connection;

	public function __construct(string $url, string $authToken = '')
	{
		if (!class_exists(\LibSQL::class, false)) {
			throw new RuntimeException(
				"Rozšíření 'libsql' (tursodatabase/turso-client-php) není v tomto PHP nahrané. " .
				'Lokálně použij driver "pdo-sqlite" (config/config.neon parametr database.driver).',
			);
		}

		$this->connection = $authToken === ''
			? new \LibSQL($url)
			: new \LibSQL(['url' => $url, 'authToken' => $authToken]);

		$this->connection->execute('PRAGMA foreign_keys = ON');
	}

	/** @param list<scalar|null> $params */
	public function fetchAll(string $sql, array $params = []): array
	{
		/** @var list<array<string, mixed>> $rows */
		$rows = $this->connection->query($sql, $params)->fetchArray(\LibSQL::LIBSQL_ASSOC);

		return $rows;
	}

	/** @param list<scalar|null> $params */
	public function fetch(string $sql, array $params = []): ?array
	{
		return $this->fetchAll($sql, $params)[0] ?? null;
	}

	/** @param list<scalar|null> $params */
	public function fetchField(string $sql, array $params = []): mixed
	{
		$row = $this->fetch($sql, $params);

		return $row === null ? null : array_values($row)[0];
	}

	/** @param list<scalar|null> $params */
	public function execute(string $sql, array $params = []): int
	{
		return $this->connection->execute($sql, $params);
	}

	public function lastInsertId(): int
	{
		return $this->connection->lastInsertedId();
	}

	public function executeScript(string $sql): void
	{
		$this->connection->executeBatch($sql);
	}

	public function transaction(callable $fn): mixed
	{
		$this->connection->execute('BEGIN');

		try {
			$result = $fn();
			$this->connection->execute('COMMIT');

			return $result;
		} catch (Throwable $e) {
			$this->connection->execute('ROLLBACK');

			throw $e;
		}
	}
}
