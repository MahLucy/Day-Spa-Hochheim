-- Migration 003b: Adiciona url_danfse se ainda não existir
-- Execute somente se a migration 003 falhou com "Duplicate column 'focusnfe_ref'"
-- Se der "Duplicate column 'url_danfse'", ignore — coluna já existe.

ALTER TABLE `invoices`
    ADD COLUMN `url_danfse` VARCHAR(500) NULL AFTER `xml_path`;
