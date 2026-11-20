# Instrucciones de Implementación: Invitación Digital Interactiva (Multi-Pareja + Control de Llegadas)

Este documento define las tareas técnicas para evolucionar el proyecto **InvitacionDigital** incorporando personalización avanzada, multimedia y animaciones interactivas (sobre de apertura, música, cuenta regresiva, calendario, galería, sugerencia de canciones) **manteniendo estrictamente la arquitectura multi-pareja actual** (cada pareja tiene su propia invitación independiente con su `token_acceso` y sus propios invitados) y mejorando la visualización y ordenamiento de llegadas en el dashboard.

## Principios No Negociables (Arquitectura Actual)

1. **Aislamiento por Pareja (1 Pareja = 1 Invitación única):**
   * Cada registro en `parejas` es un evento independiente con su propio `token_acceso`, su propia URL (`invitacion.php?token=<pareja>` o `?codigo=<invitado>`), sus propias fotos/música (`assets/uploads/{id_pareja}/`) y su propia configuración visual (`config_json`).
   * Ninguna configuración o archivo de una pareja debe cruzarse con otra.
2. **Retrocompatibilidad Total:**
   * No romper ni alterar las lógicas existentes de `invitados.php`, `asistencia.php`, `qr_checkin.php`, `qr_pdf.php` ni `exportar.php`.
   * Si una pareja existente tiene `config_json` en `NULL`, `public/invitacion.php` debe renderizarse sin errores usando los campos clásicos de la tabla `parejas`.
3. **Convenciones de `AGENTS.md`:**
   * PHP 8.3 procedural + Apache + MariaDB 11.4, sin frameworks.
   * Todo en español.
   * Iniciar scripts con `require_once __DIR__ . '/.../includes/functions.php'` y en `/admin` con `requiereLogin()`.
   * Consultas SQL preparadas con PDO (`prepare`/`execute`) y escape con `h()`.
   * Migraciones secuenciales en `sql/migrations/` compatibles con `php bin/migrate.php` (sin tocar `sql/schema.sql`).

## Fase 1: Base de Datos (Migraciones SQL)

### Tarea 1.1: Crear `sql/migrations/005_add_config_avanzada_parejas.sql`
Agregar soporte de plantilla y configuración JSON individual por pareja:

```sql
ALTER TABLE parejas
  ADD COLUMN plantilla VARCHAR(50) NOT NULL DEFAULT 'clasica' AFTER estilo,
  ADD COLUMN config_json LONGTEXT NULL AFTER plantilla;
```

### Tarea 1.2: Crear `sql/migrations/006_add_rsvp_extras_invitados.sql`
Ampliar la tabla `invitados` para capturar extras del formulario RSVP:

```sql
ALTER TABLE invitados
  ADD COLUMN cancion_sugerida VARCHAR(255) NULL AFTER mesa,
  ADD COLUMN restriccion_alimentaria VARCHAR(255) NULL AFTER cancion_sugerida;
```

## Fase 2: Helpers y Gestión Multimedia por Pareja

### Tarea 2.1: Actualizar `includes/functions.php`
Añadir las siguientes funciones auxiliares:

1. `getParejaConfig(array $pareja): array`:
   * Decodifica `$pareja['config_json']` (`json_decode(..., true)`) y lo fusiona (`array_replace_recursive`) con la estructura por defecto:
     * `hero`: `['subtitulo' => '¡Nos casamos!', 'foto_portada' => '', 'musica_url' => '', 'autoplay' => true, 'sobre_apertura' => true]`
     * `historia`: `['activo' => false, 'titulo' => 'Nuestra Historia', 'texto' => '']`
     * `eventos`: `[]` (array de objetos: `['titulo' => 'Ceremonia', 'hora' => '', 'lugar' => '', 'direccion' => '', 'maps_url' => '', 'waze_url' => '']`)
     * `vestimenta`: `['activo' => false, 'codigo' => 'Formal / Elegante', 'nota' => '', 'colores_evitar' => '']`
     * `galeria`: `[]` (array de rutas relativas en `assets/uploads/{id_pareja}/...`)
     * `regalos`: `['activo' => true, 'titulo' => 'Mesa de Regalos', 'mensaje' => '', 'link_externo' => '']` *(los datos bancarios se siguen tomando de las columnas de `parejas`)*.
     * `extras`: `['permitir_cancion' => true, 'permitir_restriccion' => true, 'whatsapp_contacto' => '', 'mensaje_whatsapp' => 'Hola, tengo una consulta sobre la boda']`

2. `subirArchivoMultimedia(array $file, int $idPareja, string $tipo = 'imagen'): ?string`:
   * Crea el directorio aislado `assets/uploads/{$idPareja}/` si no existe (`0755`).
   * Si `$tipo === 'imagen'`: valida MIME (`image/jpeg`, `image/png`, `image/webp`), redimensiona si supera `1600px` de ancho usando `GD`, guarda en formato `.webp` (calidad 82) con nombre único y retorna `assets/uploads/{$idPareja}/archivo.webp`.
   * Si `$tipo === 'audio'`: valida MIME (`audio/mpeg`, `audio/mp3`) y máx. 10MB, guarda `.mp3` y retorna `assets/uploads/{$idPareja}/archivo.mp3`.

## Fase 3: Dashboard, Ordenamiento de Llegadas y Configurador (`/admin`)

### Tarea 3.1: Mejorar `public/admin/index.php` (Dashboard Principal)
Mantener las tarjetas/resúmenes actuales por pareja y agregar una sección/panel de **Control y Ordenamiento de Invitados / Llegadas**:
1. **Selector de Pareja / Evento activo** en la parte superior (por defecto muestra la próxima boda activa o permite filtrar por `id_pareja`).
2. **Métricas rápidas del evento seleccionado:** Total Invitados, Confirmados (personas), Pendientes, Rechazados, **Ya Llegaron (Check-in)** vs **Faltan por Llegar**.
3. **Tabla interactiva de Invitados y Orden de Llegada:**
   * Mostrar: Nombre del invitado, Pases (`cantidad_personas`), **Mesa asignada**, Estado (`confirmado`, `pendiente`, `rechazado`), Estado de llegada (`llego`: Sí/No), **Hora exacta de llegada** (`hora_llegada`), Restricción alimentaria y Canción sugerida.
   * **Filtros y Ordenamiento rápido (SQL + UI):**
     * Ordenar por: *Hora de llegada (más recientes primero)*, *Número de Mesa*, *Nombre A-Z*, o *Pendientes de llegar primero*.
     * Filtrar por: *Todos*, *Ya llegaron*, *Faltan por llegar (confirmados)*, *Por Mesa*.
     * Buscador rápido por nombre o código.
   * **Acción rápida de Check-in desde el Dashboard:** Botón para marcar/desmarcar llegada (`llego = 1`, `hora_llegada = NOW()`) directamente sin salir del dashboard, además del enlace directo a `asistencia.php?pareja={id}` y `qr_checkin.php`.

### Tarea 3.2: Enlace en `public/admin/parejas.php`
* Agregar en cada fila de pareja un botón **"Personalizar Invitación"** -> `configurar_invitacion.php?id={id_pareja}`.

### Tarea 3.3: Crear `public/admin/configurar_invitacion.php`
Editor individual por pareja (`?id={id_pareja}`) protegido con `requiereLogin()`:
1. **Layout dividido (2 columnas en desktop):**
   * **Columna Izquierda (7 cols):** Formulario con pestañas para esa pareja específica:
     * **Tab 1 - Diseño y Portada:** Selector de plantilla (`clasica`, `floral`, `minimalista`, `editorial`), colores primario/secundario, toggle de *Sobre de Apertura animado*, subida de *Foto de Portada* y subida/eliminación de *Música de Fondo (MP3)*.
     * **Tab 2 - Eventos y Ubicación:** Repetidor dinámico JS para agregar/quitar bloques de evento (Ceremonia, Civil, Recepción) con Hora, Lugar, Dirección, Link Google Maps y Link Waze.
     * **Tab 3 - Historia y Galería:** Toggle y texto de "Nuestra Historia", subida múltiple de fotos a `assets/uploads/{id_pareja}/` y botón para eliminar fotos individuales.
     * **Tab 4 - Dress Code y Regalos:** Toggle y campos para Código de Vestimenta, notas, mensaje de regalos y enlace externo.
     * **Tab 5 - RSVP y Contacto:** Toggles para habilitar *Canción Sugerida* y *Restricción Alimentaria* en el RSVP público, y número de WhatsApp de contacto.
   * **Columna Derecha (5 cols):** Panel fijo (`position: sticky`) con `<iframe>` apuntando a `../invitacion.php?token={token_acceso}&preview=1` (mostrando exactamente la invitación de esa pareja) y selector de vista Móvil (`375px`) / Escritorio (`100%`).
2. **Guardado:** Actualiza solo el registro `WHERE id = :id` de esa pareja.

### Tarea 3.4: Actualizar `public/admin/invitados.php`, `asistencia.php` y `exportar.php`
* En `asistencia.php`: asegurar que la vista muestre el orden de llegada (`hora_llegada`) y permita ordenar por mesa o llegada.
* En `invitados.php` y `exportar.php`: incluir las columnas `cancion_sugerida` y `restriccion_alimentaria`.

## Fase 4: Frontend Público Interactivo (`public/invitacion.php`)

Mantener intacta la resolución por `?token=` (vista general de esa pareja) y `?codigo=` (invitado específico de esa pareja):

1. **Tema Individual:** Cargar `$cfg = getParejaConfig($pareja)`, inyectar variables CSS (`--color-primario`, `--color-secundario`) y aplicar clase `theme-{plantilla}`.
2. **Sobre de Apertura (Envelope Intro):** Si `$cfg['hero']['sobre_apertura']` está activo, pantalla inicial con sobre animado y sello lacrado con las iniciales de esa pareja. Al abrir, dispara la animación y reproduce su música MP3.
3. **Controles Flotantes:** Reproductor Play/Pause de música y botón flotante de WhatsApp de esa pareja.
4. **Secciones Dinámicas (según configuración de esa pareja):**
   * Hero con foto de portada, nombres y cuenta regresiva en tiempo real (`fecha_boda` + `hora_boda`).
   * Botón **"Agendar Fecha"** (enlace a Google Calendar + descarga de `.ics`).
   * Itinerario de eventos con botones a Google Maps y Waze.
   * Historia y Galería con visor **Lightbox** en JS vanilla.
   * Código de Vestimenta y Mesa de Regalos / Datos Bancarios con botón **"Copiar datos"**.
   * **Formulario RSVP:** Conserva validación por `codigo_invitacion` y pases asignados, sumando los campos opcionales `cancion_sugerida` y `restriccion_alimentaria` si están activos para esa pareja.

## Fase 5: Estilos (`assets/css/style.css`)

* Añadir estilos modulares para las 4 plantillas (`clasica`, `floral`, `minimalista`, `editorial`), animación 3D del sobre de apertura, lightbox de galería, tarjetas de itinerario, vista dividida del configurador y badges de estado/orden de llegada en el dashboard, manteniendo diseño **Mobile-First** y sin alterar las clases administrativas actuales.

## Orden de Ejecución Sugerido para OpenCode

1. Crear y ejecutar migraciones `005` y `006` (`php bin/migrate.php`).
2. Implementar `getParejaConfig()` y `subirArchivoMultimedia()` en `includes/functions.php`.
3. Actualizar `public/admin/index.php` con el selector de pareja y la tabla ordenable de invitados/llegadas con check-in rápido.
4. Crear `public/admin/configurar_invitacion.php` (aislado por `id_pareja`) y enlazarlo desde `public/admin/parejas.php`.
5. Actualizar `public/invitacion.php` y `assets/css/style.css`.
6. Actualizar `public/admin/invitados.php`, `asistencia.php` y `exportar.php`.
7. Probar con 2 parejas distintas verificando que cada una tenga su diseño, fotos y lista de llegadas totalmente independientes.