-- Migración 003: Agregar campos de lugar de ceremonia y fiesta a parejas
ALTER TABLE parejas
  ADD COLUMN lugar_ceremonia VARCHAR(200) NULL AFTER lugar_boda,
  ADD COLUMN lugar_fiesta   VARCHAR(200) NULL AFTER lugar_ceremonia;