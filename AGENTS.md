# AGENTS.md — InvitacionDigital

Sistema de invitaciones digitales de boda. PHP 8.3 + Apache + MariaDB 11.4, sin framework.

## Comandos

| Comando | Uso |
|---|---|
| `docker compose up --build` | Arranca app (puerto 8084) + mariadb (3308) + migraciones |
| `php bin/migrate.php` | Ejecuta migraciones pendientes (`sql/migrations/*.sql`, orden alfabético) |
| `composer install` | Dependencias: `phpspreadsheet`, `dompdf`, `endroid/qr-code` |

No hay suite de tests ni linter configurados. Verifica manualmente con `docker compose up` y navegando a `http://localhost:8084`.

## Arquitectura

```
public/            # DocumentRoot de Apache (lo único servible)
  invitacion.php   # Página pública de invitación (por token de pareja o código de invitado)
  admin/           # Panel administrativo (auth por sesión)
    index.php      # Dashboard con estadísticas por pareja
    parejas.php    # CRUD de parejas
    invitados.php  # Invitados por pareja
    asistencia.php # Asistencia y mesas (check-in)
    exportar.php   # Exportar Excel (phpspreadsheet) / PDF (dompdf)
    qr_pdf.php, qr_checkin.php  # QR (endroid/qr-code) para check-in
    login.php, logout.php
includes/
  functions.php    # Helpers globales: pdo(), h(), generarToken(), redirigir(),
                   # estaLogueado(), requiereLogin(), flash(), getFlash()
  sidebar.php      # Sidebar del panel (incluido con include)
config/
  config.php       # Constantes: APP_NAME, BASE_URL, ADMIN_URL, PUBLIC_URL
  database.php     # Conexión PDO (lee vars de entorno DB_*)
bin/migrate.php    # Runner de migraciones SQL (tabla schema_migrations)
sql/
  schema.sql       # Esquema inicial (se inyecta al crear el contenedor de BD)
  migrations/      # Migraciones incrementales numeradas: 001_..., 002_...
assets/css/        # CSS único: style.css (simbólink en public/assets)
```

Rutas web:
- Invitación pública: `/{BASE_URL}/invitacion.php?token=<pareja>` o `?codigo=<invitado>`
- Panel: `ADMIN_URL` = `/admin` (alias en `apache.conf`)

## Base de datos

MariaDB 11.4, charset `utf8mb4`. Conexión vía PDO en `config/database.php` con vars de entorno `DB_HOST`, `DB_DATABASE`, `DB_USER`, `DB_PASSWORD` (valores por defecto: `boda_invitaciones` / `sixinio` / `123456`).

Tablas:
- `parejas`: novios, fecha/lugar/hora de boda, mensaje, `token_acceso` único, personalización de colores y estilo, datos bancarios, `activo`
- `invitados`: FK `id_pareja` (CASCADE), `codigo_invitacion` único, `estado` ENUM(pendiente, confirmado, rechazado), `cantidad_personas`, `mesa`, `llego` + `hora_llegada` (check-in)
- `usuarios_admin`: admin con `password_hash` (`admin` / `admin123` por defecto)
- `configuracion`: clave-valor

## Migraciones

1. Nueva migración: `sql/migrations/NNN_descripcion.sql` (siguiente número secuencial).
2. `bin/migrate.php` la aplica una sola vez (idempotente vía `schema_migrations`).
3. `schema.sql` solo se usa al crear el contenedor de BD en limpio; para cambios incrementales **no** se edita.

## Convenciones

- Idioma: español (comentarios, UI, mensajes de error).
- Cada página PHP arranca con `require_once __DIR__ . '/.../includes/functions.php'`.
- Consultas SIEMPRE preparadas con PDO (`prepare`/`execute`), nunca concatenación de SQL.
- Escape de HTML con `h()` en toda salida de datos de la BD.
- Auth admin: `requiereLogin()` al inicio de cada página del panel; sesión con `admin_id`.
- Mensajes transitorios con `flash()` / `getFlash()`.
- Tokens/códigos con `generarToken()`.
- HTML inline en los PHP (no hay plantillas ni motor de vistas). CSS global en `assets/css/style.css`.
- composer.json declara PSR-4 `App\` → `src/` (aún sin implementar; el código real es procedural en `includes/` y `public/`). No se crea `src/` salvo refactor explícito.

## Precauciones

- `public/` es el DocumentRoot: nada sensible (`.env`, `config/`, `vendor/`) debe quedar accesible bajo `public/`.
- `apache.conf` define el VirtualHost del contenedor; cambios ahí requieren rebuild de imagen.
- La app se sirve de `public/` pero el código real vive fuera (viaje `__DIR__ . '/../...'`); mantener ese patrón.
