-- Fáze 1 — základní schéma: category, service (šablona), payment (platba za období).
-- Peníze = integer haléře. Datum/čas ISO 8601 (TEXT). `_migration` si vytváří runner sám.
-- Migrace nesmí obsahovat BEGIN/COMMIT — transakci řídí runner (MigrationRunner).

CREATE TABLE category (
	id INTEGER PRIMARY KEY,
	name TEXT NOT NULL,
	color TEXT NOT NULL,
	sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE service (
	id INTEGER PRIMARY KEY,
	name TEXT NOT NULL,
	amount INTEGER NOT NULL,
	period TEXT NOT NULL CHECK (period IN ('monthly', 'yearly')),
	due_day INTEGER NOT NULL CHECK (due_day BETWEEN 1 AND 31),
	due_month INTEGER NULL CHECK (due_month IS NULL OR due_month BETWEEN 1 AND 12),
	category_id INTEGER NULL REFERENCES category (id) ON DELETE SET NULL,
	icon TEXT NULL,
	note TEXT NULL,
	is_archived INTEGER NOT NULL DEFAULT 0 CHECK (is_archived IN (0, 1)),
	created_at TEXT NOT NULL,
	archived_at TEXT NULL,
	sort_order INTEGER NOT NULL DEFAULT 0
);

-- period_month je NOT NULL i pro roční služby (roční použije due_month) — kvůli
-- UNIQUE(service_id, period_year, period_month), viz docs/PLAN.md.
CREATE TABLE payment (
	id INTEGER PRIMARY KEY,
	service_id INTEGER NOT NULL REFERENCES service (id) ON DELETE CASCADE,
	period_year INTEGER NOT NULL,
	period_month INTEGER NOT NULL CHECK (period_month BETWEEN 1 AND 12),
	due_date TEXT NOT NULL,
	paid_date TEXT NULL,
	amount INTEGER NOT NULL,
	note TEXT NULL,
	created_at TEXT NOT NULL,
	UNIQUE (service_id, period_year, period_month)
);

CREATE INDEX idx_payment_period ON payment (period_year, period_month);
