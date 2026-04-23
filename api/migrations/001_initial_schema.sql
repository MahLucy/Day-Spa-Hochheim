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
    `category`         ENUM('Spa',    'Massagem',    'Massagem Spa Terapia',    'Drenagem Linfática',    'Banho',    'Cuidados Especiais',    'Depilação') NOT NULL,
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
-- ============================================================
--  Day Spa Hochheim – INSERT completo de serviços
--  Gerado com base no MENU_ATUALIZADO_2026
-- ============================================================
-- ---------------------------------------------------------------
-- SPAS
-- ---------------------------------------------------------------
('Spa Terapêutico',
 'Spa',
 'Massagem mista com duas técnicas (Terapêutica e Liberação Miofascial), Reflexologia podal, Banho de hidrozonioterapia e Aperitivos.',
 435.00, 120, 1, 1),

('Spa Relaxante',
 'Spa',
 'Spa de Pés e Mãos, Esfoliação corporal e facial, Máscara facial, Massagem relaxante ou drenagem linfática, Banho aromático ou purificação.',
 685.00, 210, 1, 1),

('Spa Aniversariante',
 'Spa',
 'Massagem 4 mãos, Banho Purificação, Spa dos Pés e Hidratação Facial de cortesia. Um momento especial e inesquecível.',
 540.00, 120, 1, 1),

('Spa Kids',
 'Spa',
 'Máscara facial, Massagem relaxante, Banho aromático e Aperitivos. Uma experiência mágica e divertida para crianças.',
 385.00, 90, 1, 1),

('Spa Gestantes',
 'Spa',
 'Drenagem linfática corporal e facial, Esfoliação e hidratação corporal, Banho aromático/purificação e Aperitivos.',
 490.00, 150, 1, 1),

('Spa Debutante',
 'Spa',
 'Massagem relaxante, Esfoliação corporal, Banho aromático/purificação, Manicure e pedicure e Aperitivos.',
 490.00, 225, 1, 1),

('Spa Noiva',
 'Spa',
 'Spa dos Pés e Mãos, Esfoliação corporal e facial, Máscara facial, Banho aromático/purificação, Drenagem linfática completa e Aperitivos.',
 810.00, 240, 1, 1),

('Spa Noivo',
 'Spa',
 'Spa dos Pés e Mãos, Esfoliação corporal, Máscara facial, Banho aromático/purificação, Massagem relaxante e Aperitivos.',
 730.00, 180, 1, 1),

('Spa Casal',
 'Spa',
 '02 Massagens Anti Stress, 02 Spas dos Pés e Mãos e 02 Banhos aromático/purificação. Compartilhe um momento especial com quem mais ama.',
 975.00, 150, 1, 1),

('Spa das Mãos',
 'Spa',
 'Massagem esfoliante, Massagem relaxante e Hidratação com parafina.',
 60.00, 30, 1, 1),

('Spa dos Pés',
 'Spa',
 'Massagem esfoliante, Escalda pés e Hidratação com parafina.',
 90.00, 30, 1, 1),

-- ---------------------------------------------------------------
-- MASSAGENS
-- ---------------------------------------------------------------
('Massagem Relaxante (25–30min)',
 'Massagem',
 'Movimentos firmes e suaves que relaxam músculos tensos, estimulando serotonina e endorfina para bem-estar, equilíbrio e melhora da circulação.',
 90.00, 30, 1, 1),

('Massagem Relaxante (45–60min)',
 'Massagem',
 'Movimentos firmes e suaves que relaxam músculos tensos, estimulando serotonina e endorfina para bem-estar, equilíbrio e melhora da circulação.',
 165.00, 60, 1, 1),

('Massagem Terapêutica (25–30min)',
 'Massagem',
 'Técnica especializada no alívio de pontos gatilhos — áreas de extrema sensibilidade ou dor na musculatura.',
 90.00, 30, 1, 1),

('Massagem Terapêutica (45–60min)',
 'Massagem',
 'Técnica especializada no alívio de pontos gatilhos — áreas de extrema sensibilidade ou dor na musculatura.',
 170.00, 60, 1, 1),

('Massagem Craniana',
 'Massagem',
 'Técnica terapêutica focada no crânio, pescoço, ombros e face para aliviar estresse, tensões musculares, dores de cabeça e melhorar a circulação.',
 80.00, 30, 1, 1),

('Liberação Miofascial (25–30min)',
 'Massagem',
 'Técnica manual focada na fáscia muscular para relaxar e alongar os músculos, melhorar desempenho e acelerar recuperação pós-treino.',
 100.00, 30, 1, 1),

('Liberação Miofascial (45–60min)',
 'Massagem',
 'Técnica manual focada na fáscia muscular para relaxar e alongar os músculos, melhorar desempenho e acelerar recuperação pós-treino.',
 175.00, 60, 1, 1),

('Massagem Desportiva (25–30min)',
 'Massagem',
 'Indicada para atletas. Relaxa o tecido muscular, dissolve tensões crônicas, melhora a circulação e alivia pontos de tensão.',
 95.00, 30, 1, 1),

('Massagem Desportiva (45–60min)',
 'Massagem',
 'Indicada para atletas. Relaxa o tecido muscular, dissolve tensões crônicas, melhora a circulação e alivia pontos de tensão.',
 175.00, 60, 1, 1),

('Massagem Modeladora (25–30min)',
 'Massagem',
 'Movimentos firmes e repetitivos focados em abdômen, coxas, culotes e glúteos para redução de medidas, celulite e modelagem corporal.',
 105.00, 30, 1, 1),

('Massagem Modeladora (45–60min)',
 'Massagem',
 'Movimentos firmes e repetitivos focados em abdômen, coxas, culotes e glúteos para redução de medidas, celulite e modelagem corporal.',
 210.00, 60, 1, 1),

('Massagem 4 Mãos',
 'Massagem',
 'Técnica com 2 profissionais em precisão e sincronismo. Combina Drenagem Linfática Manual com Massagem Terapêutica ou Relaxante à escolha.',
 330.00, 60, 1, 1),

-- ---------------------------------------------------------------
-- MASSAGENS SPA TERAPIA
-- ---------------------------------------------------------------
('Massagem com Pedras Quentes',
 'Massagem Spa Terapia',
 'Integra geoterapia, termoterapia e massoterapia. Pedras quentes agem nas terminações nervosas e musculatura profunda, aliviando pontos gatilho.',
 235.00, 60, 1, 1),

('Massagem Velas',
 'Massagem Spa Terapia',
 'Velas aquecidas são aplicadas em deslizamentos e compressões no corpo, promovendo alívio de tensões, hidratação intensa e aromaterapia.',
 230.00, 60, 1, 1),

('Massagem Bamboo',
 'Massagem Spa Terapia',
 'Utiliza bambus de diferentes tamanhos combinando drenagem linfática, shiatsu e técnica ayurvédica com óleos específicos para relaxamento ou estimulação.',
 220.00, 60, 1, 1),

('Massagem Pindas',
 'Massagem Spa Terapia',
 'Saquinhos de linho ou algodão com ervas medicinais aquecidas (camomila, alecrim, rosa, capim-limão). Alivia estresse, estados depressivos e eleva a autoestima.',
 295.00, 60, 1, 1),

('Massagem Conchas',
 'Massagem Spa Terapia',
 'Associa relaxamento à drenagem linfática com óleos essenciais e vegetais. Manobras combinadas com Drenagem Linfática Manual para experiência terapêutica única.',
 220.00, 60, 1, 1),

('Massagem Anti-estresse',
 'Massagem Spa Terapia',
 'Massagem clássica com óleos essenciais aliviando tensões em todo o corpo, incluindo rosto, couro cabeludo, pés e mãos.',
 220.00, 60, 1, 1),

('Massagem Integrativa',
 'Massagem Spa Terapia',
 'Reúne cinco técnicas orientais (tailandesa, zon shiatsu, ayurvédica, esalon e havaiana) para redistribuir energia, reduzir fadiga e equilibrar corpo e mente.',
 365.00, 120, 1, 1),

('Massagem com Pedras Energizantes',
 'Massagem Spa Terapia',
 'Pedras coloridas posicionadas nos chakras liberam energia para promover equilíbrio da força vital e criar conexão entre corpo e mente.',
 290.00, 90, 1, 1),

-- ---------------------------------------------------------------
-- DRENAGENS LINFÁTICAS
-- ---------------------------------------------------------------
('Drenagem Linfática Corporal (25–30min)',
 'Drenagem Linfática',
 'Movimentos suaves e rítmicos que seguem o trajeto linfático para remoção de líquidos, combate à celulite e melhora do sistema imunológico.',
 90.00, 30, 1, 1),

('Drenagem Linfática Corporal (45–60min)',
 'Drenagem Linfática',
 'Movimentos suaves e rítmicos que seguem o trajeto linfático para remoção de líquidos, combate à celulite e melhora do sistema imunológico.',
 170.00, 60, 1, 1),

('Drenagem Linfática Facial',
 'Drenagem Linfática',
 'Aplicada exclusivamente no rosto para reduzir edemas e promover melhor irrigação local, realçando a recuperação e saúde da pele facial.',
 90.00, 30, 1, 1),

('Drenagem Pós-Operatório (30min)',
 'Drenagem Linfática',
 'Manobras específicas sobre o sistema linfático superficial para drenar excesso de líquido acumulado e amenizar o processo inflamatório pós-cirúrgico.',
 95.00, 30, 1, 1),

('Drenagem Pós-Operatório (60min)',
 'Drenagem Linfática',
 'Manobras específicas sobre o sistema linfático superficial para drenar excesso de líquido acumulado e amenizar o processo inflamatório pós-cirúrgico.',
 175.00, 60, 1, 1),

-- ---------------------------------------------------------------
-- BANHOS
-- ---------------------------------------------------------------
('Banho Detox',
 'Banho',
 'Banho com sais, óleo essencial de lavanda e vinagre de maçã a 38–39°C para desintoxicação profunda e eliminação de impurezas.',
 135.00, 30, 1, 1),

('Banho Hidrozonioterapia',
 'Banho',
 'Banho anti-inflamatório indicado para exaustão, insônia, depressão, fibromialgia, varizes e edemas. Melhora a circulação e elimina toxinas.',
 165.00, 30, 1, 1),

('Banho Aromático',
 'Banho',
 'Hidromassagem com sais de banho de morango ou chocolate. Proporciona relaxamento, hidratação e fragrância envolvente para a pele.',
 135.00, 30, 1, 1),

('Banho Purificação',
 'Banho',
 'Hidromassagem com sais enriquecidos por ervas medicinais (arruda, manjericão, alecrim, sálvia, cravo, canela e malva). Recomendado para mudanças bruscas de energia.',
 135.00, 30, 1, 1),

-- ---------------------------------------------------------------
-- CUIDADOS ESPECIAIS
-- ---------------------------------------------------------------
('Limpeza de Pele',
 'Cuidados Especiais',
 'Remove impurezas, células mortas, cravos, espinhas e millus, desobstruindo poros e controlando oleosidade para uma pele mais saudável.',
 280.00, 90, 1, 1),

('Revitalização Facial',
 'Cuidados Especiais',
 'Higienização da pele, esfoliação facial e hidratação com máscara, deixando a pele com aspecto mais saudável e rejuvenescido.',
 165.00, 45, 1, 1),

('Banho de Lua – Completo',
 'Cuidados Especiais',
 'Descoloração dos pelos do corpo com parafina inicial para diminuir irritação. Inclui aplicação de água oxigenada volume 30 e pó descolorante.',
 210.00, 45, 1, 1),

('Banho de Lua – Parcial',
 'Cuidados Especiais',
 'Descoloração parcial dos pelos do corpo com parafina inicial para diminuir irritação. Inclui aplicação de água oxigenada volume 30 e pó descolorante.',
 120.00, 35, 1, 1),

('Banho de Lua + Gomagem',
 'Cuidados Especiais',
 'Combinação de descoloração dos pelos (Banho de Lua) com esfoliação corporal profunda (Gomagem) em uma única sessão.',
 300.00, 60, 1, 1),

('Gomagem',
 'Cuidados Especiais',
 'Esfoliação corporal milenar que remove células mortas, impurezas e toxinas, estimulando a microcirculação e preparando a pele para absorção de ativos.',
 125.00, 45, 1, 1),

('Esfoliação Corporal',
 'Cuidados Especiais',
 'Tratamento de esfoliação corporal para remover células mortas e renovar a pele, deixando-a mais suave e preparada para hidratação.',
 125.00, 45, 1, 1),

('Hidrozonioterapia dos Pés',
 'Cuidados Especiais',
 'Imersão dos pés em água ozonizada combinando os benefícios da ozonioterapia com os efeitos terapêuticos da água para experiência relaxante e revitalizante.',
 90.00, 30, 1, 1),

('Massagem Relaxante nos Pés',
 'Cuidados Especiais',
 'Técnica manual de massagem nos pés que melhora a circulação sanguínea e proporciona relaxamento profundo após um dia cansativo.',
 75.00, 30, 1, 1),

('Reflexologia Podal',
 'Cuidados Especiais',
 'Terapia complementar que estimula pontos reflexos nos pés para tratar o corpo como um todo, oferecendo benefícios físicos e mentais.',
 105.00, 30, 1, 1),

('Manicure',
 'Cuidados Especiais',
 'Higienização, corte de unhas e retirada de cutículas nas mãos com opção de esmaltação.',
 45.00, 30, 1, 1),

('Pedicure',
 'Cuidados Especiais',
 'Higienização, corte de unhas e retirada de cutículas nos pés com opção de esmaltação.',
 55.00, 45, 1, 1),

-- ---------------------------------------------------------------
-- DEPILAÇÃO
-- ---------------------------------------------------------------
('Depilação – Sobrancelha/Buço/Face (Espanhola)',
 'Depilação',
 'Depilação com técnica espanhola (cera puxada pela própria cera) para sobrancelha, buço e face.',
 35.00, 20, 1, 1),

('Depilação – Axila (Espanhola)',
 'Depilação',
 'Depilação com técnica espanhola (cera puxada pela própria cera) para região das axilas.',
 35.00, 20, 1, 1),

('Depilação – Virilha Completa (Espanhola)',
 'Depilação',
 'Depilação completa da virilha com técnica espanhola.',
 70.00, 30, 1, 1),

('Depilação – Virilha Fio (Espanhola)',
 'Depilação',
 'Depilação virilha fio com técnica espanhola.',
 57.00, 30, 1, 1),

('Depilação – Virilha Contorno (Espanhola)',
 'Depilação',
 'Depilação virilha contorno com técnica espanhola.',
 60.00, 30, 1, 1),

('Depilação – Virilha M/M (Espanhola)',
 'Depilação',
 'Depilação virilha meia mais meia com técnica espanhola.',
 65.00, 30, 1, 1),

('Depilação – Virilha C/M (Espanhola)',
 'Depilação',
 'Depilação virilha cavada mais meia com técnica espanhola.',
 65.00, 30, 1, 1),

('Depilação – Masculina Peito ou Costas (Espanhola)',
 'Depilação',
 'Depilação masculina de peito ou costas com técnica espanhola.',
 95.00, 30, 1, 1),

('Depilação – Meia Perna (Roll On)',
 'Depilação',
 'Depilação de meia perna com técnica roll on (cera removida com papel).',
 55.00, 30, 1, 1),

('Depilação – Perna Completa (Roll On)',
 'Depilação',
 'Depilação de perna completa com técnica roll on (cera removida com papel).',
 90.00, 60, 1, 1),

-- ---------------------------------------------------------------
-- OUTROS CUIDADOS
-- ---------------------------------------------------------------
('Henna Sobrancelha',
 'Cuidados Especiais',
 'Sobrancelhas delineadas e tingidas com henna para desenho duradouro que permanece por até duas semanas.',
 65.00, 40, 1, 1),

('Corrente Russa',
 'Cuidados Especiais',
 'Eletroestimulação que induz contração muscular, aumentando força e volume. Melhora o abdômen, pernas e glúteos, reduz flacidez e aprimora a drenagem linfática.',
 95.00, 30, 1, 1),

('Ultrassom',
 'Cuidados Especiais',
 'Utilização de ondas ultrassônicas direcionadas a áreas específicas para quebra de células de gordura localizada.',
 90.00, 30, 1, 1),

('Reiki',
 'Cuidados Especiais',
 'Imposição das mãos para harmonizar padrões energéticos do corpo, promovendo relaxamento profundo, conforto e equilíbrio holístico.',
 165.00, 60, 1, 1),

('Auriculoterapia',
 'Cuidados Especiais',
 'Estimulação de pontos reflexos na orelha correspondentes a órgãos específicos do corpo para tratamento de problemas e alívio de sintomas.',
 145.00, NULL, 1, 1);