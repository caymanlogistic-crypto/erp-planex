-- Migration 032: Add client document types (idempotent)
INSERT IGNORE INTO `document_types` (`name`, `code`, `entity_type`, `category`, `sort_order`) VALUES
('Карточка предприятия', 'company_card', 'client', 'predefined', 1),
('Свидетельство ИНН', 'inn_cert', 'client', 'predefined', 2),
('Свидетельство ОГРН', 'ogrn_cert', 'client', 'predefined', 3),
('Договор', 'contract', 'client', 'predefined', 4);
