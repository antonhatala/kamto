// @ts-check
// Runs once before the whole suite (inside the e2e container): resets the app to a known
// clean state so the run is deterministic and repeatable.
//
// - Recreates var/kamto.db from the app's real migrations/*.sql files (same SQL and
//   `_migration` bookkeeping as App\Database\MigrationRunner), via Node's built-in
//   `node:sqlite` — no PHP or host tools needed. chmod 666 keeps the file writable for
//   php-fpm's www-data user (the e2e container runs as root).
// - Removes the login-throttle state file (temp/login-throttle.json, see config.neon) so
//   wrong-password tests always start with a zero failure counter.
const fs = require('fs');
const path = require('path');
const { DatabaseSync } = require('node:sqlite');

const repoRoot = path.resolve(__dirname, '../..');

module.exports = async () => {
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

	fs.rmSync(path.join(repoRoot, 'temp/login-throttle.json'), { force: true });
};
