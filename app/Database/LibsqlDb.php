<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;
use Throwable;

/**
 * Nativní libSQL driver — primární pro produkci (Bunny Database, Fáze 6). Používá
 * extension `tursodatabase/turso-client-php`, která registruje globální třídu `\LibSQL`
 * (žádný `Libsql\` namespace).
 *
 * POZOR: tahle extension v aktuálním image (Fáze 1) není nainstalovaná — psáno „na slepo"
 * podle upstream dokumentace (https://github.com/tursodatabase/turso-client-php/blob/main/
 * docs/LibSQL-class.md), bez možnosti ověřit chování proti reálnému rozšíření. Guard
 * v konstruktoru vyhodí jasnou výjimku, když extension chybí — lokálně/v testech se místo
 * toho použije PdoSqliteDb (config/config.neon `database.driver: pdo-sqlite`).
 *
 * PHPStan vidí signatury `\LibSQL`/`\LibSQLResult` přes `stubs/LibSQL.stub.php`
 * (phpstan.neon `parameters.scanFiles`) — nejde o autoloadovaný kód, jen deklarace pro
 * statickou analýzu, takže PHPStan projde i bez nahrané extension.
 *
 * Transakce: `\LibSQL::transaction()` sice vrací samostatný `\LibSQLTransaction` objekt
 * s vlastním execute()/commit()/rollback(), ale dokumentace neuvádí, že by měl i query()
 * nebo executeBatch() — místo skládání přes ten objekt proto transaction() pouští prosté
 * `BEGIN`/`COMMIT`/`ROLLBACK` jako běžné SQL přes hlavní spojení (SQLite/libSQL je bere
 * jako obyčejné příkazy). Díky tomu zůstávají fetchAll/fetch/execute/executeScript
 * triviální — pořád jede jen jedno spojení, žádné přepínání mezi handly. Tenhle
 * předpoklad je potřeba ověřit, až extension přibude do image (Fáze 6).
 */
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
