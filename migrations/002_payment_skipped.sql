-- Fáze 3 — přeskočení platby (viz CONTEXT.md, pojem „Přeskočeno"). Reverzibilní pauza:
-- paid_date NULL a skipped_at NOT NULL. Migrace nesmí obsahovat BEGIN/COMMIT — transakci
-- řídí runner (MigrationRunner), stejně jako 001_init.sql.

ALTER TABLE payment ADD COLUMN skipped_at TEXT NULL;
