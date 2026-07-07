<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Tenká DB brána — jediný způsob, jak repozitáře mluví s úložištěm. Poziční `?`
 * placeholdery, prepared statements, žádná interpolace vstupů do SQL.
 *
 * Implementace: PdoSqliteDb (PDO sqlite, lokální default) a LibsqlDb (nativní libSQL
 * extension, produkce na Bunny). Výběr přes App\Database\DbFactory podle configu.
 */
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
	 * 1. sloupec 1. řádku (nebo null, když dotaz nic nevrátil).
	 * @param list<scalar|null> $params
	 */
	public function fetchField(string $sql, array $params = []): mixed;

	/**
	 * @param list<scalar|null> $params
	 * @return int počet ovlivněných řádků
	 */
	public function execute(string $sql, array $params = []): int;

	/** Hodnota po jiném příkazu než INSERT je nedefinovaná. */
	public function lastInsertId(): int;

	/**
	 * Provede víc SQL příkazů najednou (oddělených `;`) — použití: migrace. Sám o sobě
	 * není transakční/atomický (SQLite autocommit per statement); pro atomicitu obal
	 * do transaction().
	 */
	public function executeScript(string $sql): void;

	/**
	 * Obalí $fn do transakce: commit při úspěchu, rollback + rethrow při výjimce.
	 * Není vnořitelná (re-entrantní) — vnořené volání selže.
	 *
	 * @template T
	 * @param callable(): T $fn
	 * @return T
	 */
	public function transaction(callable $fn): mixed;
}
