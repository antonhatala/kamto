-- Ruční řazení služeb (sort_order + moveUp/moveDown) nahrazeno automatickým řazením podle
-- dne splatnosti (is_sliding, due_day, id) — viz ServiceRepository::findAll()/findArchived().
-- Sloupec service.sort_order se tím stává mrtvým. Migrace nesmí obsahovat BEGIN/COMMIT —
-- transakci řídí runner (MigrationRunner), stejně jako 001_init.sql/002_payment_skipped.sql/
-- 003_service_sliding.sql.

ALTER TABLE service DROP COLUMN sort_order;
