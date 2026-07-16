-- Klouzavá služba (viz CONTEXT.md) — nemá pevný den splatnosti, proto nikdy nedá stav "po
-- splatnosti" (Overdue), viz App\Payment\PaymentStatus::derive. Default 0 = beze změny pro
-- existující služby. Migrace nesmí obsahovat BEGIN/COMMIT — transakci řídí runner
-- (MigrationRunner), stejně jako 001_init.sql/002_payment_skipped.sql.

ALTER TABLE service ADD COLUMN is_sliding INTEGER NOT NULL DEFAULT 0 CHECK (is_sliding IN (0, 1));
