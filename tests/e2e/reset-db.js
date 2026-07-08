// @ts-check
// Resets the app to a known clean state so runs are deterministic and repeatable. Shared by
// the suite-wide global-setup and by specs that need an isolated database mid-run (e.g. the
// dashboard tests, which assert on aggregate totals and must not see leftover services).
//
// - Recreates var/kamto.db from the app's real migrations/*.sql files, applied in numeric
//   order (001_init, 002_payment_skipped, …) with the same `_migration` bookkeeping as
//   App\Database\MigrationRunner — via Node's built-in `node:sqlite`, so no PHP or host tools
//   are needed. chmod 666 keeps the file writable for php-fpm's www-data user (the e2e
//   container runs as root).
// - Optionally clears the login-throttle state file (temp/login-throttle.json, see
//   config.neon) so wrong-password tests always start from a zero failure counter.
const fs = require('fs');
const path = require('path');
const { DatabaseSync } = require('node:sqlite');

const repoRoot = path.resolve(__dirname, '../..');

/**
 * @param {{ resetThrottle?: boolean }} [options]
 *   resetThrottle: also delete the login-throttle state file (default true).
 */
function resetDb({ resetThrottle = true } = {}) {
	const dbFile = path.join(repoRoot, 'var/kamto.db');
	for (const suffix of ['', '-wal', '-shm', '-journal']) {
		fs.rmSync(dbFile + suffix, { force: true });
	}

	const db = new DatabaseSync(dbFile);
	db.exec('CREATE TABLE IF NOT EXISTS _migration (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

	const migrationsDir = path.join(repoRoot, 'migrations');
	const files = fs.readdirSync(migrationsDir).filter((file) => file.endsWith('.sql')).sort();
	for (const file of files) {
		db.exec(fs.readFileSync(path.join(migrationsDir, file), 'utf8'));
		db.prepare('INSERT INTO _migration (version, applied_at) VALUES (?, ?)')
			.run(file.replace(/\.sql$/, ''), new Date().toISOString());
	}
	db.close();
	fs.chmodSync(dbFile, 0o666);

	if (resetThrottle) {
		fs.rmSync(path.join(repoRoot, 'temp/login-throttle.json'), { force: true });
	}
}

module.exports = { resetDb };
