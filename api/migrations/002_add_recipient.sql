-- =============================================================
-- Day Spa Hochheim — Migration v1.1
-- Adiciona campos de presenteado na tabela orders
-- =============================================================

ALTER TABLE `orders`
    ADD COLUMN `recipient_name`  VARCHAR(255) NULL AFTER `notes`,
    ADD COLUMN `recipient_email` VARCHAR(255) NULL AFTER `recipient_name`,
    ADD COLUMN `recipient_phone` VARCHAR(20)  NULL AFTER `recipient_email`,
    ADD KEY `idx_recipient_name` (`recipient_name`);
