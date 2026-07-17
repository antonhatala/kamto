<?php

declare(strict_types=1);

namespace App\Database;

interface Db
{
	/**
	 * @param list<scalar|null> $params
	 * @return list<array<string, mixed>>
	 */
	public function fetchAll(string $sql, array $params = []): array;

	/**
	 * @param list<scalar|null> $params
	 * @return array<string, mixed>|null
	 */
	public function fetch(string $sql, array $params = []): ?array;

	/**
	 * @param list<scalar|null> $params
	 */
	public function fetchField(string $sql, array $params = []): mixed;

	/**
	 * @param list<scalar|null> $params
	 * @return int
	 */
	public function execute(string $sql, array $params = []): int;

	public function lastInsertId(): int;

	public function executeScript(string $sql): void;

	/**
	 * @template T
	 * @param callable(): T $fn
	 * @return T
	 */
	public function transaction(callable $fn): mixed;
}
