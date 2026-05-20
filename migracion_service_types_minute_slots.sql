ALTER TABLE fields
    ADD COLUMN IF NOT EXISTS service_type VARCHAR(30) NOT NULL DEFAULT 'football' AFTER field_type,
    ADD COLUMN IF NOT EXISTS block_minutes INT NOT NULL DEFAULT 60 AFTER service_type,
    ADD COLUMN IF NOT EXISTS price_unit_label VARCHAR(80) NOT NULL DEFAULT 'por hora' AFTER block_minutes;

UPDATE fields
SET service_type = IFNULL(NULLIF(service_type, ''), 'football'),
    block_minutes = CASE WHEN block_minutes IS NULL OR block_minutes = 0 THEN 60 ELSE block_minutes END,
    price_unit_label = IFNULL(NULLIF(price_unit_label, ''), 'por hora');

INSERT INTO fields (name, floor_type, sizes, ilumination, field_type, service_type, block_minutes, price_unit_label, roofed, value, ilumination_value, elements_rent, disabled)
SELECT 'Pádel', '', '', 0, 'Pádel', 'padel', 90, 'por bloque de 1:30', 0, 0, 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM fields WHERE service_type = 'padel');

INSERT INTO fields (name, floor_type, sizes, ilumination, field_type, service_type, block_minutes, price_unit_label, roofed, value, ilumination_value, elements_rent, disabled)
SELECT 'Quincho', '', '', 0, 'Quincho', 'quincho', 60, 'por hora', 0, 0, 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM fields WHERE service_type = 'quincho');

INSERT INTO fields (name, floor_type, sizes, ilumination, field_type, service_type, block_minutes, price_unit_label, roofed, value, ilumination_value, elements_rent, disabled)
SELECT 'Eventos / Confitería', '', '', 0, 'Eventos / Confitería', 'eventos', 60, 'por hora', 0, 0, 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM fields WHERE service_type = 'eventos');
