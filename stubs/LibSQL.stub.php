<?php

declare(strict_types=1);

// PHPStan-only stub pro nativní extension tursodatabase/turso-client-php (`ext-libsql`).
// Za běhu se nikdy nenačítá ani neautoloaduje — je zapojený jen přes phpstan.neon
// `parameters.scanFiles` (deklaruje kompletně nové, jinak neznámé třídy — proto scanFiles,
// ne stubFiles), aby statická analýza App\Database\LibsqlDb prošla i bez nahrané extension
// (ta v image Fáze 1 chybí). Signatury omezené na to, co LibsqlDb reálně volá; převzato
// z upstream dokumentace:
// https://github.com/tursodatabase/turso-client-php/blob/main/docs/LibSQL-class.md
// Až extension do image přibude (Fáze 6), tenhle stub zkontroluj/smaž, kdyby se lišil.

final class LibSQL
{
	public const LIBSQL_ASSOC = 1;
	public const LIBSQL_NUM = 2;
	public const LIBSQL_BOTH = 3;
	public const LIBSQL_ALL = 4;
	public const LIBSQL_LAZY = 5;
	public const OPEN_READONLY = 1;
	public const OPEN_READWRITE = 2;
	public const OPEN_CREATE = 4;

	/** @param string|array<string, string> $config */
	public function __construct(
		string|array $config,
		?bool $sqldOfflineMode = false,
		?int $flags = 6,
		?string $encryptionKey = '',
		?bool $offlineWrites = false,
	) {
	}

	/** @param list<scalar|null> $parameters */
	public function execute(string $stmt, ?array $parameters = []): int
	{
	}

	public function executeBatch(string $stmt): bool
	{
	}

	/** @param list<scalar|null> $parameters */
	public function query(string $stmt, array $parameters = [], bool $forceRemote = false): LibSQLResult
	{
	}

	public function lastInsertedId(): int
	{
	}

	public function close(): void
	{
	}
}

final class LibSQLResult
{
	/** @return list<array<string, mixed>> */
	public function fetchArray(int $mode = LibSQL::LIBSQL_ASSOC): array
	{
	}
}
