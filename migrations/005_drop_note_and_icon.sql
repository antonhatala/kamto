-- Zrušení poznámek a emoji ikon (issue 2026-07): pole Poznámka (service.note i payment.note)
-- a Ikona (service.icon) mizí z celé aplikace — sloupce jsou tím mrtvé, uložená data se
-- záměrně zahazují. Migrace nesmí obsahovat BEGIN/COMMIT — transakci řídí runner
-- (MigrationRunner), stejně jako 004_drop_service_sort_order.sql.

ALTER TABLE service DROP COLUMN icon;
ALTER TABLE service DROP COLUMN note;
ALTER TABLE payment DROP COLUMN note;
