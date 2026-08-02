-- ============================================================
-- Migración afiliados v3: anular comisiones + datos de cobro
-- Ejecutar UNA vez en phpMyAdmin → SQL.
--
-- Cambios:
--   1) afiliados_ventas: columnas anulada / anulada_at / anulada_motivo
--      para soportar reembolsos que reviertan la comisión.
--   2) afiliados: columnas payout_method / payout_iban / payout_phone /
--      payout_paypal / payout_titular para que cada afiliado configure
--      cómo quiere recibir su comisión.
-- ============================================================

-- 1) Anular comisión en venta atribuida
ALTER TABLE `afiliados_ventas`
    ADD COLUMN `anulada`        TINYINT(1)    NOT NULL DEFAULT 0   AFTER `pagada_referencia`,
    ADD COLUMN `anulada_at`     DATETIME      NULL                  AFTER `anulada`,
    ADD COLUMN `anulada_motivo` VARCHAR(255)  NULL                  AFTER `anulada_at`,
    ADD KEY `idx_anulada` (`anulada`);

-- 2) Datos de cobro del afiliado
ALTER TABLE `afiliados`
    ADD COLUMN `payout_method`  ENUM('transferencia','bizum','paypal') NULL AFTER `notas`,
    ADD COLUMN `payout_iban`    VARCHAR(34)  NULL AFTER `payout_method`,
    ADD COLUMN `payout_phone`   VARCHAR(20)  NULL AFTER `payout_iban`,
    ADD COLUMN `payout_paypal`  VARCHAR(255) NULL AFTER `payout_phone`,
    ADD COLUMN `payout_titular` VARCHAR(120) NULL AFTER `payout_paypal`;
