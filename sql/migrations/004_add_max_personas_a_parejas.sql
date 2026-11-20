-- Migración 004: Agregar columna max_personas a parejas (límite configurable por invitación)
ALTER TABLE parejas ADD COLUMN max_personas INT NOT NULL DEFAULT 2;