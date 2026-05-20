-- Actualizacion de servicios, tarifas, disponibilidad y logs.
-- Seguro para ejecutar mas de una vez en MySQL/MariaDB.

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(40) NOT NULL,
    opening_time TIME NOT NULL DEFAULT '07:00:00',
    closing_time TIME NOT NULL DEFAULT '23:00:00',
    duration_minutes INT NOT NULL DEFAULT 60,
    slot_interval_minutes INT NOT NULL DEFAULT 60,
    minimum_duration_minutes INT NOT NULL DEFAULT 60,
    booking_interval_minutes INT NOT NULL DEFAULT 60,
    active TINYINT(1) NOT NULL DEFAULT 1,
    online_available TINYINT(1) NOT NULL DEFAULT 1,
    allows_quincho_addon TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    color VARCHAR(20) NULL,
    icon VARCHAR(80) NULL,
    offer_active TINYINT(1) NOT NULL DEFAULT 0,
    offer_text VARCHAR(255) NULL,
    discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    offer_start_date DATE NULL,
    offer_end_date DATE NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_prices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id INT UNSIGNED NOT NULL,
    base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    charge_type ENUM('hour','block','event') NOT NULL DEFAULT 'hour',
    deposit_price DECIMAL(12,2) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_prices_service_active (service_id, active),
    CONSTRAINT fk_service_prices_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(80) NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_logs_admin_id (admin_id),
    KEY idx_admin_logs_entity (entity_type, entity_id),
    KEY idx_admin_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_duration_minutes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'duration_minutes'
);
SET @sql := IF(@has_duration_minutes = 0,
    'ALTER TABLE services ADD COLUMN duration_minutes INT NOT NULL DEFAULT 60 AFTER closing_time',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_slot_interval_minutes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'slot_interval_minutes'
);
SET @sql := IF(@has_slot_interval_minutes = 0,
    'ALTER TABLE services ADD COLUMN slot_interval_minutes INT NOT NULL DEFAULT 60 AFTER duration_minutes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_minimum_duration_minutes := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'minimum_duration_minutes'
);
SET @sql := IF(@has_minimum_duration_minutes = 0,
    'ALTER TABLE services ADD COLUMN minimum_duration_minutes INT NOT NULL DEFAULT 60 AFTER slot_interval_minutes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_booking_interval_minutes := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'booking_interval_minutes'
);
SET @sql := IF(@has_booking_interval_minutes = 0,
    'ALTER TABLE services ADD COLUMN booking_interval_minutes INT NOT NULL DEFAULT 60 AFTER minimum_duration_minutes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_active := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'active'
);
SET @sql := IF(@has_active = 0,
    'ALTER TABLE services ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER booking_interval_minutes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_online_available := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'online_available'
);
SET @sql := IF(@has_online_available = 0,
    'ALTER TABLE services ADD COLUMN online_available TINYINT(1) NOT NULL DEFAULT 1 AFTER active',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_allows_quincho_addon := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'allows_quincho_addon'
);
SET @sql := IF(@has_allows_quincho_addon = 0,
    'ALTER TABLE services ADD COLUMN allows_quincho_addon TINYINT(1) NOT NULL DEFAULT 1 AFTER online_available',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_display_order := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'display_order'
);
SET @sql := IF(@has_display_order = 0,
    'ALTER TABLE services ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER allows_quincho_addon',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_color := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'color'
);
SET @sql := IF(@has_color = 0,
    'ALTER TABLE services ADD COLUMN color VARCHAR(20) NULL AFTER display_order',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_icon := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'icon'
);
SET @sql := IF(@has_icon = 0,
    'ALTER TABLE services ADD COLUMN icon VARCHAR(80) NULL AFTER color',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_offer_active := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'offer_active'
);
SET @sql := IF(@has_offer_active = 0,
    'ALTER TABLE services ADD COLUMN offer_active TINYINT(1) NOT NULL DEFAULT 0 AFTER icon',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_offer_text := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'offer_text'
);
SET @sql := IF(@has_offer_text = 0,
    'ALTER TABLE services ADD COLUMN offer_text VARCHAR(255) NULL AFTER offer_active',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_discount_type := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'discount_type'
);
SET @sql := IF(@has_discount_type = 0,
    "ALTER TABLE services ADD COLUMN discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage' AFTER offer_text",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_discount_value := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'discount_value'
);
SET @sql := IF(@has_discount_value = 0,
    'ALTER TABLE services ADD COLUMN discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_offer_start_date := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'offer_start_date'
);
SET @sql := IF(@has_offer_start_date = 0,
    'ALTER TABLE services ADD COLUMN offer_start_date DATE NULL AFTER discount_value',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_offer_end_date := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'offer_end_date'
);
SET @sql := IF(@has_offer_end_date = 0,
    'ALTER TABLE services ADD COLUMN offer_end_date DATE NULL AFTER offer_start_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO services
    (name, code, opening_time, closing_time, duration_minutes, slot_interval_minutes, minimum_duration_minutes, booking_interval_minutes, active, online_available, allows_quincho_addon, display_order, color, icon)
VALUES
    ('Cancha / Fútbol', 'football', '07:00:00', '23:00:00', 60, 60, 60, 60, 1, 1, 1, 10, '#198754', 'fa-futbol'),
    ('Pádel', 'padel', '07:00:00', '23:00:00', 90, 90, 90, 90, 1, 1, 1, 20, '#0d6efd', 'fa-table-tennis-paddle-ball'),
    ('Quincho', 'quincho', '07:00:00', '23:00:00', 60, 60, 60, 60, 1, 1, 0, 30, '#6c757d', 'fa-champagne-glasses'),
    ('Eventos / Confitería', 'eventos', '07:00:00', '23:00:00', 60, 60, 60, 60, 1, 1, 1, 40, '#dc3545', 'fa-calendar-check')
ON DUPLICATE KEY UPDATE
    name = CASE WHEN services.name IS NULL OR services.name = '' THEN VALUES(name) ELSE services.name END,
    opening_time = COALESCE(services.opening_time, VALUES(opening_time)),
    closing_time = COALESCE(services.closing_time, VALUES(closing_time)),
    duration_minutes = CASE WHEN services.duration_minutes IS NULL OR services.duration_minutes = 0 THEN VALUES(duration_minutes) ELSE services.duration_minutes END,
    slot_interval_minutes = CASE WHEN services.slot_interval_minutes IS NULL OR services.slot_interval_minutes = 0 THEN VALUES(slot_interval_minutes) ELSE services.slot_interval_minutes END,
    minimum_duration_minutes = CASE WHEN services.minimum_duration_minutes IS NULL OR services.minimum_duration_minutes = 0 THEN VALUES(minimum_duration_minutes) ELSE services.minimum_duration_minutes END,
    booking_interval_minutes = CASE WHEN services.booking_interval_minutes IS NULL OR services.booking_interval_minutes = 0 THEN VALUES(booking_interval_minutes) ELSE services.booking_interval_minutes END,
    display_order = CASE WHEN services.display_order IS NULL OR services.display_order = 0 THEN VALUES(display_order) ELSE services.display_order END,
    code = VALUES(code);

INSERT INTO service_prices (service_id, base_price, charge_type, active)
SELECT s.id,
       COALESCE(NULLIF(MAX(f.value), 0), 0),
       CASE s.code WHEN 'padel' THEN 'block' WHEN 'eventos' THEN 'hour' ELSE 'hour' END,
       1
FROM services s
LEFT JOIN fields f ON f.service_type = s.code
WHERE NOT EXISTS (
    SELECT 1 FROM service_prices sp WHERE sp.service_id = s.id AND sp.active = 1
)
GROUP BY s.id, s.code;

SET @has_service_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fields'
      AND COLUMN_NAME = 'service_id'
);
SET @sql := IF(@has_service_id = 0,
    'ALTER TABLE fields ADD COLUMN service_id INT UNSIGNED NULL AFTER field_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_opening_time := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'opening_time'
);
SET @sql := IF(@has_opening_time = 0,
    "ALTER TABLE services ADD COLUMN opening_time TIME NOT NULL DEFAULT '07:00:00' AFTER code",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_closing_time := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'services' AND COLUMN_NAME = 'closing_time'
);
SET @sql := IF(@has_closing_time = 0,
    "ALTER TABLE services ADD COLUMN closing_time TIME NOT NULL DEFAULT '23:00:00' AFTER opening_time",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_duration_minutes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'duration_minutes'
);
SET @sql := IF(@has_duration_minutes = 0,
    'ALTER TABLE services ADD COLUMN duration_minutes INT NOT NULL DEFAULT 60 AFTER closing_time',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_slot_interval_minutes := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'slot_interval_minutes'
);
SET @sql := IF(@has_slot_interval_minutes = 0,
    'ALTER TABLE services ADD COLUMN slot_interval_minutes INT NOT NULL DEFAULT 60 AFTER duration_minutes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_booking_status := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'status'
);
SET @sql := IF(@has_booking_status = 0,
    "ALTER TABLE bookings ADD COLUMN status ENUM('disponible','pendiente','confirmada','cancelada','expirada') NOT NULL DEFAULT 'confirmada' AFTER approved",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_booking_group := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bookings'
      AND COLUMN_NAME = 'booking_group_id'
);
SET @sql := IF(@has_booking_group = 0,
    'ALTER TABLE bookings ADD COLUMN booking_group_id VARCHAR(64) NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE fields f
JOIN services s ON s.code = COALESCE(NULLIF(f.service_type, ''), 'football')
SET f.service_id = s.id,
    f.service_type = s.code,
    f.block_minutes = COALESCE(NULLIF(s.duration_minutes, 0), s.minimum_duration_minutes),
    f.price_unit_label = CASE
        WHEN COALESCE(NULLIF(s.duration_minutes, 0), s.minimum_duration_minutes) = 60 THEN 'por hora'
        ELSE CONCAT('por bloque de ', COALESCE(NULLIF(s.duration_minutes, 0), s.minimum_duration_minutes), ' min')
    END
WHERE f.service_id IS NULL OR f.service_type IS NULL OR f.service_type = '';

UPDATE services
SET duration_minutes = CASE WHEN duration_minutes IS NULL OR duration_minutes = 0 THEN minimum_duration_minutes ELSE duration_minutes END,
    slot_interval_minutes = CASE WHEN slot_interval_minutes IS NULL OR slot_interval_minutes = 0 THEN booking_interval_minutes ELSE slot_interval_minutes END,
    minimum_duration_minutes = CASE WHEN minimum_duration_minutes IS NULL OR minimum_duration_minutes = 0 THEN duration_minutes ELSE minimum_duration_minutes END,
    booking_interval_minutes = CASE WHEN booking_interval_minutes IS NULL OR booking_interval_minutes = 0 THEN slot_interval_minutes ELSE booking_interval_minutes END;

UPDATE bookings
SET status = CASE
    WHEN annulled = 1 THEN 'cancelada'
    WHEN approved = 1 OR mp = 1 THEN 'confirmada'
    ELSE 'pendiente'
END
WHERE status IS NULL OR status = 'disponible';

UPDATE booking_slots
SET status = 'expired', active = 0
WHERE status = 'pending'
  AND active = 1
  AND expires_at IS NOT NULL
  AND expires_at < NOW();
