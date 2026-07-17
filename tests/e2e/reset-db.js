// @ts-check
const fs = require('fs');
const path = require('path');
const { DatabaseSync } = require('node:sqlite');

const repoRoot = path.resolve(__dirname, '../..');

/**
 * @param {{ resetThrottle?: boolean }} [options]
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
