ALTER TABLE invitados
  ADD COLUMN cancion_sugerida VARCHAR(255) NULL AFTER mesa,
  ADD COLUMN restriccion_alimentaria VARCHAR(255) NULL AFTER cancion_sugerida;