ALTER TABLE parejas
  ADD COLUMN plantilla VARCHAR(50) NOT NULL DEFAULT 'clasica' AFTER estilo,
  ADD COLUMN config_json LONGTEXT NULL AFTER plantilla;