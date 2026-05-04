-- Migration 003: Migração NFe.io → Focus NFe
-- Adiciona referência Focus NFe e URL do DANFSe na tabela invoices

ALTER TABLE `invoices`
    ADD COLUMN `focusnfe_ref` VARCHAR(100) NULL AFTER `provider_invoice_id`,
    ADD COLUMN `url_danfse`   VARCHAR(500) NULL AFTER `xml_path`,
    MODIFY COLUMN `provider`  VARCHAR(50)  NOT NULL DEFAULT 'focusnfe';
