<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;

final class MigrationRunner
{
	public function __construct(
		private readonly Db $db,
		private readonly string $migrationsDir,
	) {
	}

	/** @return list<string> */
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
