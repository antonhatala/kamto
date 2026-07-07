<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOStatement;
use Throwable;

/**
 * PDO-backed implementace Db brány — lokální/dev default (`var/kamto.db`) a přenositelný
 * fallback pro jakýkoli hosting s obyčejným souborovým systémem (bez libSQL extension).
 * Primární driver pro produkci je LibsqlDb (Bunny, Fáze 6).
 */
final class PdoSqliteDb implements Db
{
	private readonly PDO $pdo;

	public function __construct(string $path)
	{
		$this->pdo = new PDO('sqlite:' . $path);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->exec('PRAGMA foreign_keys = ON');
	}

	/** @param list<scalar|null> $params */
	public function fetchAll(string $sql, array $params = []): array
	{
		/** @var list<array<string, mixed>> $rows */
		$rows = $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

		return $rows;
	}

	/** @param list<scalar|null> $params */
	public function fetch(string $sql, array $params = []): ?array
	{
		$row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

		return $row === false ? null : $row;
	}

	/** @param list<scalar|null> $params */
	public function fetchField(string $sql, array $params = []): mixed
	{
		$value = $this->run($sql, $params)->fetchColumn();

		return $value === false ? null : $value;
	}

	/** @param list<scalar|null> $params */
	public function execute(string $sql, array $params = []): int
	{
		return $this->run($sql, $params)->rowCount();
	}

	public function lastInsertId(): int
	{
		return (int) $this->pdo->lastInsertId();
	}

	public function executeScript(string $sql): void
	{
		// PDO_SQLite's exec() (unlike PDO_MySQL) runs `;`-separated multi-statement scripts
		// in one call — no PDO::MYSQL_ATTR_MULTI_STATEMENTS equivalent needed.
		$this->pdo->exec($sql);
	}

	public function transaction(callable $fn): mixed
	{
		$this->pdo->beginTransaction();

		try {
			$result = $fn();
			$this->pdo->commit();

			return $result;
		} catch (Throwable $e) {
			$this->pdo->rollBack();

			throw $e;
		}
	}

	/** @param list<scalar|null> $params */
	private function run(string $sql, array $params): PDOStatement
	{
		$statement = $this->pdo->prepare($sql);
		$statement->execute($params);

		return $statement;
	}
}
