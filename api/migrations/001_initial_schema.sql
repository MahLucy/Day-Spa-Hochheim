-- =============================================================
-- Day Spa Hochheim — Schema Completo v1.0
-- MySQL 8 / utf8mb4_unicode_ci
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -------------------------------------------------------------
-- services
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(255)    NOT NULL,
    `category`         ENUM('Massagem','Terapias','Estética','Energia') NOT NULL,
    `description`      TEXT            NULL,
    `price`            DECIMAL(10,2)   NOT NULL,
    `duration_minutes` INT             NOT NULL DEFAULT 60,
    `sessions`         INT             NOT NULL DEFAULT 1,
    `image_path`       VARCHAR(500)    NULL,
    `active`           TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_active`   (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- customers
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `cpf`        VARCHAR(14)  NOT NULL,
    `birthdate`  DATE         NULL,
    `gender`     ENUM('M','F','O') NULL,
    `email`      VARCHAR(255) NOT NULL,
    `phone`      VARCHAR(20)  NULL,
    `whatsapp`   VARCHAR(20)  NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cpf`   (`cpf`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- orders
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_id`      INT UNSIGNED  NOT NULL,
    `status`           ENUM('pending','paid','processing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `subtotal`         DECIMAL(10,2) NOT NULL,
    `discount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total`            DECIMAL(10,2) NOT NULL,
    `gift_card_format` ENUM('digital','printed') NOT NULL DEFAULT 'digital',
    `notes`            TEXT          NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer`   (`customer_id`),
    KEY `idx_status`     (`status`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- order_items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`         INT UNSIGNED  NOT NULL,
    `service_id`       INT UNSIGNED  NOT NULL,
    `service_snapshot` JSON          NOT NULL,
    `quantity`         INT           NOT NULL DEFAULT 1,
    `unit_price`       DECIMAL(10,2) NOT NULL,
    `subtotal`         DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order_id`   (`order_id`),
    KEY `idx_service_id` (`service_id`),
    CONSTRAINT `fk_order_items_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- payments
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`              INT UNSIGNED  NOT NULL,
    `provider`              VARCHAR(50)   NOT NULL DEFAULT 'mercadopago',
    `provider_payment_id`   VARCHAR(100)  NULL,
    `provider_preference_id` VARCHAR(100) NULL,
    `status`                ENUM('pending','approved','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `method`                ENUM('pix','credit_card','debit_card','boleto') NULL,
    `amount`                DECIMAL(10,2) NOT NULL,
    `installments`          INT           NOT NULL DEFAULT 1,
    `paid_at`               DATETIME      NULL,
    `raw_response`          JSON          NULL,
    `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id`            (`order_id`),
    KEY `idx_provider_payment_id` (`provider_payment_id`),
    KEY `idx_status`              (`status`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- gift_cards
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gift_cards` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`         INT UNSIGNED NOT NULL,
    `code`             VARCHAR(12)  NOT NULL,
    `qr_code_path`     VARCHAR(500) NULL,
    `pdf_path`         VARCHAR(500) NULL,
    `valid_until`      DATE         NOT NULL,
    `status`           ENUM('active','redeemed','expired','cancelled') NOT NULL DEFAULT 'active',
    `redeemed_at`      DATETIME     NULL,
    `redeemed_by`      VARCHAR(255) NULL,
    `redemption_notes` TEXT         NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code`     (`code`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_status`   (`status`),
    CONSTRAINT `fk_gift_cards_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- gift_card_items  (snapshot imutável dos serviços)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gift_card_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gift_card_id`     INT UNSIGNED NOT NULL,
    `service_name`     VARCHAR(255) NOT NULL,
    `service_duration` INT          NOT NULL,
    `service_category` VARCHAR(100) NOT NULL,
    `quantity`         INT          NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_gift_card_id` (`gift_card_id`),
    CONSTRAINT `fk_gift_card_items_card` FOREIGN KEY (`gift_card_id`) REFERENCES `gift_cards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- invoices
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`            INT UNSIGNED NOT NULL,
    `provider`            VARCHAR(50)  NOT NULL DEFAULT 'nfeio',
    `provider_invoice_id` VARCHAR(100) NULL,
    `status`              ENUM('pending','issued','cancelled','error') NOT NULL DEFAULT 'pending',
    `pdf_path`            VARCHAR(500) NULL,
    `xml_path`            VARCHAR(500) NULL,
    `invoice_number`      VARCHAR(50)  NULL,
    `issued_at`           DATETIME     NULL,
    `error_message`       TEXT         NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_status`   (`status`),
    CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- admin_logs (auditoria)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action`     VARCHAR(100) NOT NULL,
    `entity`     VARCHAR(100) NOT NULL,
    `entity_id`  INT UNSIGNED NULL,
    `admin_user` VARCHAR(255) NULL,
    `ip`         VARCHAR(45)  NULL,
    `details`    JSON         NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entity`     (`entity`, `entity_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- Seed: serviços iniciais
-- =============================================================
INSERT INTO `services` (`name`, `category`, `description`, `price`, `duration_minutes`, `sessions`, `active`) VALUES
('Massagem Relaxante',         'Massagem',  'Massagem corporal completa com técnicas suaves para alívio do estresse e relaxamento profundo dos músculos.',        120.00,  60, 1, 1),
('Massagem Desportiva',        'Massagem',  'Técnica especializada para recuperação muscular e melhora do desempenho físico para atletas e praticantes de esporte.', 150.00,  60, 1, 1),
('Massagem com Pedras Quentes','Massagem',  'Massagem com pedras vulcânicas aquecidas que penetram o calor nas camadas musculares para relaxamento profundo.',        180.00,  90, 1, 1),
('Drenagem Linfática',         'Massagem',  'Técnica manual que estimula o sistema linfático, reduz inchaço e melhora a circulação.',                               130.00,  60, 1, 1),
('Massagem Shiatsu',           'Massagem',  'Técnica japonesa de pressão em pontos de energia (meridianos) para equilíbrio do corpo e da mente.',                   140.00,  60, 1, 1),
('Reflexologia Podal',         'Terapias',  'Técnica que trabalha pontos reflexos nos pés conectados a órgãos e sistemas do corpo, promovendo equilíbrio geral.',    100.00,  50, 1, 1),
('Aromaterapia',               'Terapias',  'Terapia com óleos essenciais naturais para equilíbrio físico e emocional, aplicados por massagem e inalação.',          130.00,  60, 1, 1),
('Cromoterapia',               'Terapias',  'Uso terapêutico das cores da luz para harmonizar o corpo e estimular a cura natural.',                                  90.00,   50, 1, 1),
('Terapia com Cristais',       'Terapias',  'Posicionamento de cristais e pedras nos chakras para limpeza e reequilíbrio da energia vital.',                         110.00,  60, 1, 1),
('Limpeza de Pele Básica',     'Estética',  'Higienização profunda com esfoliação, extração e hidratação para pele limpa e radiante.',                               90.00,   60, 1, 1),
('Limpeza de Pele Profunda',   'Estética',  'Protocolo completo com alta frequência, eletroporação e máscara específica para cada tipo de pele.',                    130.00,  80, 1, 1),
('Peeling Químico',            'Estética',  'Tratamento com ácidos para renovação celular, uniformização do tom e redução de manchas.',                              160.00,  60, 1, 1),
('Hidratação Facial',          'Estética',  'Protocolo intensivo de hidratação com séruns e máscara para pele desidratada e sem viço.',                              100.00,  50, 1, 1),
('Microagulhamento',           'Estética',  'Técnica com microagulhas que estimula a produção de colágeno para firmeza e rejuvenescimento da pele.',                 220.00,  60, 1, 1),
('Design de Sobrancelha',      'Estética',  'Modelagem personalizada das sobrancelhas com henna ou pigmentação para realce do olhar.',                               60.00,   40, 1, 1),
('Depilação Cera (Pernas)',    'Estética',  'Depilação completa das pernas com cera quente de baixa temperatura, suave para a pele.',                                80.00,   50, 1, 1),
('Manicure e Pedicure',        'Estética',  'Cuidado completo de mãos e pés com esmaltação em gel de longa duração.',                                                75.00,   90, 1, 1),
('Reiki',                      'Energia',   'Canalização de energia universal para equilíbrio dos chakras, bem-estar e harmonia entre corpo, mente e espírito.',      80.00,   50, 1, 1),
('Meditação Guiada',           'Energia',   'Sessão guiada de meditação com técnicas de respiração para redução do estresse e reconexão interior.',                  70.00,   45, 1, 1),
('Florais de Bach',            'Energia',   'Terapia com essências florais para tratamento emocional e restabelecimento do equilíbrio interior.',                     90.00,   50, 1, 1),
('Acupuntura',                 'Energia',   'Técnica milenar da medicina oriental que estimula pontos de energia para tratamento de dores e desequilíbrios.',         150.00,  60, 1, 1),
('Banho de Ofurô',             'Terapias',  'Imersão relaxante em banheira de madeira com água aquecida e ervas aromáticas de acordo com o objetivo terapêutico.',   110.00,  40, 1, 1);
