-- Script SQL consolidado de migraciones (MySQL/MariaDB)
-- Proyecto: ReservasLaBarca
-- Generado: 2026-02-19

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- 2023-08-01-192146_Fields
CREATE TABLE IF NOT EXISTS `fields` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `floor_type` VARCHAR(250) NOT NULL,
  `sizes` VARCHAR(250) NOT NULL,
  `ilumination` BIT(1) NOT NULL,
  `field_type` VARCHAR(250) NOT NULL,
  `roofed` BIT(1) NOT NULL,
  `value` FLOAT NOT NULL,
  `ilumination_value` FLOAT NOT NULL,
  `elements_rent` BIT(1) NOT NULL,
  `disabled` BIT(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-01-201413_Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user` VARCHAR(100) NULL,
  `password` VARCHAR(255) NOT NULL,
  `superadmin` BIT(1) NULL,
  `name` VARCHAR(255) NULL,
  `active` BIT(1) NOT NULL DEFAULT b'1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-03-151058_Time
CREATE TABLE IF NOT EXISTS `time` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `from` VARCHAR(10) NOT NULL,
  `until` VARCHAR(10) NOT NULL,
  `from_cut` VARCHAR(10) NULL,
  `until_cut` VARCHAR(10) NULL,
  `nocturnal_time` VARCHAR(10) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-04-135043_Customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `dni` VARCHAR(20) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `city` VARCHAR(150) NOT NULL,
  `offer` BIT(1) NULL DEFAULT b'0',
  `quantity` INT(50) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-18-203821_Booking
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_field` INT(11) UNSIGNED NULL,
  `date` DATE NULL,
  `time_from` VARCHAR(20) NULL,
  `time_until` VARCHAR(20) NULL,
  `name` VARCHAR(50) NULL,
  `phone` VARCHAR(50) NULL,
  `total_payment` BIT(1) NULL,
  `total` FLOAT NULL,
  `parcial` FLOAT NULL,
  `diference` FLOAT NULL,
  `reservation` FLOAT NULL,
  `payment` FLOAT NULL,
  `payment_method` VARCHAR(50) NULL,
  `approved` BIT(1) NULL,
  `use_offer` BIT(1) NULL,
  `description` VARCHAR(250) NULL,
  `annulled` BIT(1) NULL DEFAULT b'0',
  `booking_time` DATETIME NULL,
  `id_customer` INT(11) UNSIGNED NULL,
  `id_preference_parcial` VARCHAR(250) NOT NULL,
  `id_preference_total` VARCHAR(250) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_id_unique` (`id`),
  KEY `bookings_id_field_foreign` (`id_field`),
  KEY `bookings_id_customer_foreign` (`id_customer`),
  CONSTRAINT `bookings_id_field_foreign` FOREIGN KEY (`id_field`) REFERENCES `fields` (`id`),
  CONSTRAINT `bookings_id_customer_foreign` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-18-205358_Payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` INT(11) UNSIGNED NULL,
  `id_booking` INT(11) UNSIGNED NOT NULL,
  `id_customer` INT(11) UNSIGNED NULL,
  `id_mercado_pago` INT(11) UNSIGNED NULL,
  `amount` FLOAT NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_id_user_foreign` (`id_user`),
  KEY `payments_id_booking_foreign` (`id_booking`),
  KEY `payments_id_customer_foreign` (`id_customer`),
  CONSTRAINT `payments_id_user_foreign` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_id_booking_foreign` FOREIGN KEY (`id_booking`) REFERENCES `bookings` (`id`),
  CONSTRAINT `payments_id_customer_foreign` FOREIGN KEY (`id_customer`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-18-225541_Rate
CREATE TABLE IF NOT EXISTS `rate` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `value` INT(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-08-31-205216_MercadoPago
CREATE TABLE IF NOT EXISTS `mercado_pago` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `collection_id` VARCHAR(50) NOT NULL,
  `collection_status` VARCHAR(50) NOT NULL,
  `payment_id` VARCHAR(50) NOT NULL,
  `status` VARCHAR(11) NOT NULL,
  `external_reference` VARCHAR(50) NULL,
  `payment_type` VARCHAR(20) NOT NULL,
  `merchant_order_id` VARCHAR(50) NOT NULL,
  `preference_id` VARCHAR(250) NOT NULL,
  `site_id` VARCHAR(20) NOT NULL,
  `processing_mode` VARCHAR(20) NOT NULL,
  `merchant_account_id` VARCHAR(50) NULL,
  `annulled` BIT(1) NULL DEFAULT b'0',
  `id_booking` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mercado_pago_id_booking_foreign` (`id_booking`),
  CONSTRAINT `mercado_pago_id_booking_foreign` FOREIGN KEY (`id_booking`) REFERENCES `bookings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-09-12-125320_Offers
CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `value` INT(10) NOT NULL,
  `description` VARCHAR(500) NULL,
  `expiration_date` DATE NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2023-10-04-155042_Uploads
CREATE TABLE IF NOT EXISTS `uploads` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2026-02-10-090000_Localities
CREATE TABLE IF NOT EXISTS `localities` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `localities_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2026-02-10-090100_BookingLocality
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `locality` VARCHAR(100) NULL AFTER `phone`;

-- 2026-02-10-100000_BookingSlots
CREATE TABLE IF NOT EXISTS `booking_slots` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL,
  `id_field` INT(11) UNSIGNED NOT NULL,
  `time_from` VARCHAR(20) NOT NULL,
  `time_until` VARCHAR(20) NOT NULL,
  `booking_id` INT(11) UNSIGNED NULL,
  `status` VARCHAR(20) NOT NULL,
  `active` BIT(1) NOT NULL DEFAULT b'1',
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_slots_active` (`date`, `id_field`, `time_from`, `time_until`, `active`),
  KEY `booking_slots_booking_id_index` (`booking_id`),
  KEY `booking_slots_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2026-02-10-110000_CancelReservations
CREATE TABLE IF NOT EXISTS `cancel_reservations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cancel_date` DATE NOT NULL,
  `field_id` INT(11) UNSIGNED NULL,
  `field_label` VARCHAR(100) NOT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `cancel_reservations_cancel_date_index` (`cancel_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- 2026-02-10-120000_Configuracion
CREATE TABLE IF NOT EXISTS `ta_configuracion` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(100) NOT NULL,
  `valor` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ta_configuracion_clave_unique` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `ta_configuracion` (`clave`, `valor`)
VALUES
  (
    'texto_cierre',
    'Aviso importante\n\nQueremos informarles que el dia <fecha> las canchas permaneceran cerradas.\nPedimos disculpas por las molestias que esto pueda ocasionar.\n\nDe todas formas, ya pueden reservar normalmente las horas para fechas posteriores.\nMuchas gracias por la comprension y por seguir eligiendonos.'
  )
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);

-- 2026-08-11_CustomerOffers
CREATE TABLE IF NOT EXISTS `customer_offers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) UNSIGNED NOT NULL,
  `value` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `description` VARCHAR(255) NULL,
  `expiration_date` DATE NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `apply_all_fields` TINYINT(1) NOT NULL DEFAULT 0,
  `apply_all_services` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_offers_customer` (`customer_id`),
  CONSTRAINT `fk_customer_offers_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `customer_offer_fields` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_offer_id` INT(11) UNSIGNED NOT NULL,
  `field_id` INT(11) UNSIGNED NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_offer_fields_offer_field` (`customer_offer_id`, `field_id`),
  CONSTRAINT `fk_customer_offer_fields_offer` FOREIGN KEY (`customer_offer_id`) REFERENCES `customer_offers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_offer_fields_field` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `customer_offer_services` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_offer_id` INT(11) UNSIGNED NOT NULL,
  `service_code` VARCHAR(40) NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_offer_services_offer_service` (`customer_offer_id`, `service_code`),
  CONSTRAINT `fk_customer_offer_services_offer` FOREIGN KEY (`customer_offer_id`) REFERENCES `customer_offers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_offer_services_service` FOREIGN KEY (`service_code`) REFERENCES `services` (`code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `customer_offer_id` INT(11) UNSIGNED NULL AFTER `id_customer`,
  ADD COLUMN IF NOT EXISTS `original_total` DECIMAL(12,2) NULL AFTER `customer_offer_id`,
  ADD COLUMN IF NOT EXISTS `discount_percentage` DECIMAL(5,2) NULL AFTER `original_total`,
  ADD COLUMN IF NOT EXISTS `discount_amount` DECIMAL(12,2) NULL AFTER `discount_percentage`;

UPDATE `bookings`
SET `original_total` = `total`
WHERE `original_total` IS NULL;

UPDATE `bookings`
SET `discount_percentage` = 0
WHERE `discount_percentage` IS NULL;

UPDATE `bookings`
SET `discount_amount` = 0
WHERE `discount_amount` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
