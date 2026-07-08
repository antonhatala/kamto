// @ts-check
// Runs once before the whole suite (inside the e2e container): resets the app DB and the
// login-throttle counter to a known clean state so the run is deterministic and repeatable.
// The actual work lives in reset-db.js (also reused by specs that reset mid-run).
const { resetDb } = require('./reset-db');

module.exports = async () => {
	resetDb();
};
