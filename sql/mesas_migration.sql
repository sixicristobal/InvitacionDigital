-- Migración: gestión de mesas y check-in de llegada
-- Ejecutar una vez: mysql -u <user> -p boda_invitaciones < mesas_migration.sql

ALTER TABLE invitados ADD COLUMN mesa VARCHAR(30) NULL AFTER cantidad_personas;
ALTER TABLE invitados ADD COLUMN llego TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE invitados ADD COLUMN hora_llegada DATETIME NULL;

-- schema.sql ya incluye estas columnas para instalaciones nuevas.
