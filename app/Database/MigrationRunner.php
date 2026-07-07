<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;

/**
 * Aplikuje migrations/NNN_*.sql podle stavu v tabulce `_migration` (kterou si sám vytvoří).
 * Každá migrace + zápis do `_migration` proběhne v jedné transakci (Db::transaction) — při
 * výjimce transakce rollbackne a výjimka propadne volajícímu (bin/migrate.php nastaví
 * nenulový exit kód).
 *
 * POZOR: migrační SQL nesmí obsahovat vlastní BEGIN/COMMIT — transakci řídí runner
 * a Db::transaction() není vnořitelná, explicitní BEGIN uvnitř migrace by selhal.
 */
final class MigrationRunner
{
	public function __construct(
		private readonly Db $db,
		private readonly string $migrationsDir,
	) {
	}

	/** @return list<string> verze migrací aplikovaných při tomto běhu (prázdné pole = no-op) */
	public function run(): array
	{
		$this->db->executeScript(
			'CREATE TABLE IF NOT EXISTS _migration (
				version TEXT PRIMARY KEY,
				applied_at TEXT NOT NULL
			)',
		);

		/** @var list<string> $applied */
		$applied = array_column($this->db->fetchAll('SELECT version FROM _migration'), 'version');

		$files = glob($this->migrationsDir . '/*.sql') ?: [];
		sort($files, SORT_STRING);

		$appliedNow = [];
		foreach ($files as $file) {
			$version = basename($file, '.sql');
			if (in_array($version, $applied, true)) {
				continue;
			}

			$sql = file_get_contents($file);
			if ($sql === false) {
				throw new RuntimeException("Nelze přečíst migraci {$version}.");
			}

			$this->db->transaction(function () use ($sql, $version): void {
				$this->db->executeScript($sql);
				$this->db->execute(
					'INSERT INTO _migration (version, applied_at) VALUES (?, ?)',
					[$version, date(DATE_ATOM)],
				);
			});

			$appliedNow[] = $version;
		}

		return $appliedNow;
	}
}
